<?php
/**
 * api.php — HarvestHub JSON API
 *
 * Routes are grouped by role. All routes return { ok: bool, ... } JSON.
 * Every write action re-validates on the server and uses prepared PDO
 * statements — client-side checks in the dashboards are for UX only.
 *
 *  AUTH (public)
 *    POST action=login        { email, password }
 *    POST action=logout
 *
 *  CUSTOMER (requires customer session)
 *    GET  action=list                 -> exchange listings (search, min_qty)
 *    POST action=create               -> new exchange listing
 *    POST action=claim                -> claim a listing
 *    GET  action=my_plot              -> the gardener's plot + application status
 *    POST action=apply_plot           -> apply for an available plot
 *    GET  action=my_croplog           -> the gardener's crop log entries
 *    POST action=croplog_create       -> add a crop log entry
 *    GET  action=resources            -> resource catalogue + availability
 *    POST action=resource_request     -> request a resource
 *    POST action=cancel_resource_request -> cancel a still-pending request
 *    GET  action=my_resource_requests -> the gardener's own requests
 *
 *  STAFF (requires staff session)
 *    GET  action=pending_applications
 *    POST action=process_application  { app_id, decision: approve|reject }
 *    GET  action=pending_resource_txns
 *    POST action=process_resource_txn { txn_id, decision: approve|reject }
 *    GET  action=all_plots
 *
 *  ADMIN (requires admin session)
 *    GET  action=stats
 *    GET  action=accounts
 *    POST action=process_coordinator_application { application_id, decision, reason }
 *    POST action=delete_account       { table: gardener|coordinator, id }
 *    POST action=send_archive_notice  { id, reason, details }
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

function sendResetEmail($toEmail, $resetLink) {
    // Your Bird API Key
    $apiKey = 'bk_eu1_5vCFHtcgJ9G2iPdwf5EIaYT4AbFB5'; 
    
    // Bird requires you to use the regional host that matches your key prefix (eu1)
    $apiUrl = 'https://eu1.platform.bird.com/v1/email/messages';

    $htmlContent = "
        <h2>HarvestHub Password Reset</h2>
        <p>You requested a password reset. Click the link below to set a new password:</p>
        <p><a href='{$resetLink}'>Reset Password</a></p>
        <p>If you did not request this, please ignore this email.</p>
    ";

    $payload = [
        'from' => [
            // During onboarding, you must use this exact testing email address
            'email' => 'onboarding@messagebird.dev', 
            'name' => 'HarvestHub'
        ],
        'to' => [$toEmail],
        'subject' => 'Reset your HarvestHub password',
        'html' => $htmlContent
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 1. Check if the server failed to connect entirely
    if ($curlError) {
        respond(['ok' => false, 'error' => "Connection Error: " . $curlError], 500);
    }
    
    // 2. Check if Bird rejected the email (HTTP codes 400 and above are errors)
    if ($httpCode >= 400) {
        respond(['ok' => false, 'error' => "Bird API Error: " . $response], 500);
    }
}

header('Content-Type: application/json');

$pdo = getDb();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function requireJsonRole(string $role): array {
    $user = currentUser();
    if (!$user || !in_array($role, $user['roles'], true)) {
        respond(['ok' => false, 'error' => 'Not authorized.'], 403);
    }
    $user['id'] = $user['ids'][$role] ?? $user['id'];
    $user['role'] = $role;
    return $user;
}

function requireInventoryManager(): array {
    $user = currentUser();
    if (!$user || (!in_array('staff', $user['roles'], true) && !in_array('admin', $user['roles'], true))) {
        respond(['ok' => false, 'error' => 'Not authorized.'], 403);
    }
    $role = in_array('staff', $user['roles'], true) ? 'staff' : 'admin';
    $user['id'] = $user['ids'][$role] ?? $user['id'];
    $user['role'] = $role;
    return $user;
}

function syncLegacyCommunityPlots(PDO $pdo): void {
    $pdo->beginTransaction();
    try {
        $legacyPlots = $pdo->query("
            SELECT CP.PlotName,
                   CASE WHEN CP.Status = 'Occupied' AND G.GardenerID IS NOT NULL THEN G.GardenerID ELSE NULL END AS GardenerID,
                   CASE WHEN CP.Status = 'Occupied' AND G.GardenerID IS NOT NULL THEN G.Name ELSE NULL END AS GardenerName,
                   CASE WHEN CP.Status = 'Occupied' AND G.GardenerID IS NOT NULL THEN 'Occupied' ELSE 'Available' END AS PlotStatus
            FROM COMMUNITY_PLOTS CP
            LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = CP.OccupantID
            WHERE NOT EXISTS (
                SELECT 1 FROM PLOT P WHERE LOWER(P.Label) = LOWER(CP.PlotName)
            )
        ")->fetchAll(PDO::FETCH_ASSOC);
        $existing = $pdo->prepare('SELECT PltID FROM PLOT WHERE LOWER(Label) = LOWER(?) LIMIT 1 FOR UPDATE');
        $insert = $pdo->prepare('INSERT INTO PLOT (Label, GardenerID, Status) VALUES (?, ?, ?)');
        foreach ($legacyPlots as $legacyPlot) {
            $existing->execute([$legacyPlot['PlotName']]);
            if ($existing->fetchColumn()) continue;
            $gardenerId = $legacyPlot['GardenerID'] === null ? null : (int) $legacyPlot['GardenerID'];
            $insert->execute([$legacyPlot['PlotName'], $gardenerId, $legacyPlot['PlotStatus']]);
            recordPlotEvent($pdo, 'Plot Added', 'system', 'Legacy plot synchronization', $legacyPlot['PlotName'], (int) $pdo->lastInsertId(), $gardenerId, $legacyPlot['GardenerName']);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

// Adds an approved quantity onto the gardener's existing borrower assignment for
// this resource (if one already exists) instead of creating a second row, so a
// gardener only ever has one "Approved" line per resource with the totals summed.
function mergeOrCreateApprovalRow(PDO $pdo, int $gardenerId, int $resourceId, int $qty, int $coordId, ?int $pltId): void {
    $existing = $pdo->prepare("SELECT TxnID FROM RESOURCE_TXN WHERE GardenerID = ? AND ResourceID = ? AND Status = 'Approved' FOR UPDATE");
    $existing->execute([$gardenerId, $resourceId]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $pdo->prepare("UPDATE RESOURCE_TXN SET Qty = Qty + ?, ApprovedAt = NOW() WHERE TxnID = ?")
            ->execute([$qty, (int) $existingId]);
    } else {
        $pdo->prepare("INSERT INTO RESOURCE_TXN (GardenerID, CoordID, ResourceID, PltID, Qty, Status, ApprovedAt) VALUES (?, ?, ?, ?, ?, 'Approved', NOW())")
            ->execute([$gardenerId, $coordId, $resourceId, $pltId, $qty]);
    }
}

// Same idea for return requests: fold the requested-back quantity into the
// gardener's existing "Return Requested" row for this resource, if any.
function mergeOrCreateReturnRequestRow(PDO $pdo, int $gardenerId, int $resourceId, int $qty, int $coordId, ?int $pltId, string $reason): void {
    $existing = $pdo->prepare("SELECT TxnID FROM RESOURCE_TXN WHERE GardenerID = ? AND ResourceID = ? AND Status = 'Return Requested' FOR UPDATE");
    $existing->execute([$gardenerId, $resourceId]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $pdo->prepare("UPDATE RESOURCE_TXN SET Qty = Qty + ?, ReturnRequestedAt = NOW(), CoordID = ?, RejectionReason = ? WHERE TxnID = ?")
            ->execute([$qty, $coordId, $reason, (int) $existingId]);
    } else {
        $pdo->prepare("INSERT INTO RESOURCE_TXN (GardenerID, CoordID, ResourceID, PltID, Qty, Status, ApprovedAt, ReturnRequestedAt, RejectionReason) VALUES (?, ?, ?, ?, ?, 'Return Requested', NOW(), NOW(), ?)")
            ->execute([$gardenerId, $coordId, $resourceId, $pltId, $qty, $reason]);
    }
}

function recordResourceEvent(PDO $pdo, int $resourceId, string $eventType, int $qty, string $actorType, string $actorName, ?int $gardenerId = null, ?string $gardenerName = null, ?int $coordId = null, ?int $pltId = null, ?string $plotLabel = null): void {
    $stmt = $pdo->prepare("INSERT INTO RESOURCE_EVENT (ResourceID, EventType, Qty, ActorType, ActorName, GardenerID, GardenerName, CoordID, PltID, PlotLabel, OccurredAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$resourceId, $eventType, $qty, $actorType, $actorName, $gardenerId, $gardenerName, $coordId, $pltId, $plotLabel]);
}

function recordPlotEvent(PDO $pdo, string $eventType, string $actorType, string $actorName, string $plotLabel, ?int $plotId = null, ?int $gardenerId = null, ?string $gardenerName = null, ?int $coordId = null, ?int $appId = null): void {
    $stmt = $pdo->prepare("INSERT INTO PLOT_EVENT (AppID, PltID, PlotLabel, EventType, ActorType, ActorName, GardenerID, GardenerName, CoordID, OccurredAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$appId, $plotId, $plotLabel, $eventType, $actorType, $actorName, $gardenerId, $gardenerName, $coordId]);
}

// Resource request limits (per gardener): how many may wait for a coordinator
// at once, and how many may be sent (including ones later cancelled) in a
// short window, so request/cancel loops can't flood the coordinator queue.
const MAX_PENDING_RESOURCE_REQUESTS = 5;
const RESOURCE_REQUEST_RATE_LIMIT = 10;
const RESOURCE_REQUEST_RATE_WINDOW_MINUTES = 10;

// Whitelisted sort options for the Exchange Board
const SORT_OPTIONS = [
    'newest'   => 'L.CreatedAt DESC',
    'oldest'   => 'L.CreatedAt ASC',
    'qty_high' => 'L.Qty DESC',
    'qty_low'  => 'L.Qty ASC',
];

const NCR_CITIES = [
    'Caloocan', 'Las Piñas', 'Makati', 'Malabon', 'Mandaluyong', 'Manila',
    'Marikina', 'Muntinlupa', 'Navotas', 'Parañaque', 'Pasay', 'Pasig',
    'Pateros', 'Quezon City', 'San Juan', 'Taguig', 'Valenzuela',
];

try {
    switch ($action) {

        // ---------------- AUTH ----------------

        case 'login': {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                respond(['ok' => false, 'error' => 'Please fill in all fields.'], 422);
            }

            $userRecord = null;
            $role = null;
            $accountTable = null;
            $attemptColumn = null;

            // Look up the address in every account state so locked accounts can
            // receive a clear notice and failed attempts can be recorded.
            $stmt = $pdo->prepare("SELECT GardenerID as id, Name, PasswordHash, COALESCE(NULLIF(Status, ''), 'Active') AS Status, FailedLoginAttempts FROM COMMUNITY_GARDENER WHERE Email = ?");
            $stmt->execute([$email]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $userRecord = $row;
                $role = 'customer';
                $accountTable = 'COMMUNITY_GARDENER';
                $attemptColumn = 'GardenerID';
            }

            // 2. Check if the user is a Garden Coordinator
            if (!$userRecord) {
                $stmt = $pdo->prepare("SELECT CoordID as id, Name, PasswordHash, COALESCE(NULLIF(Status, ''), 'Active') AS Status, FailedLoginAttempts FROM GARDEN_COORDINATOR WHERE Email = ?");
                $stmt->execute([$email]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $userRecord = $row;
                    $role = 'staff';
                    $accountTable = 'GARDEN_COORDINATOR';
                    $attemptColumn = 'CoordID';
                }
            }

            // 3. Check if the user is a System Administrator
            if (!$userRecord) {
                $stmt = $pdo->prepare("SELECT AdminID as id, Name, PasswordHash, COALESCE(NULLIF(Status, ''), 'Active') AS Status, FailedLoginAttempts FROM SYSTEM_ADMINISTRATOR WHERE Email = ?");
                $stmt->execute([$email]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $userRecord = $row;
                    $role = 'admin';
                    $accountTable = 'SYSTEM_ADMINISTRATOR';
                    $attemptColumn = 'AdminID';
                }
            }

            if ($userRecord && $userRecord['Status'] === 'Disabled') {
                respond(['ok' => false, 'error' => 'Your account has been disabled due to multiple failed login attempts. Please contact the administrator to have your account enabled.'], 403);
            }
            $passwordValid = $userRecord && $userRecord['Status'] === 'Active'
                ? password_verify($password, $userRecord['PasswordHash'])
                : false;
            if (!$userRecord || !$passwordValid) {
                if ($userRecord && $userRecord['Status'] === 'Active') {
                    // Check the old count before incrementing. MySQL evaluates
                    // UPDATE assignments from left to right, so this order makes
                    // the third failed attempt (old count = 2) disable the account.
                    $update = $pdo->prepare("UPDATE $accountTable SET Status = IF(FailedLoginAttempts >= 2, 'Disabled', COALESCE(NULLIF(Status, ''), 'Active')), FailedLoginAttempts = LEAST(FailedLoginAttempts + 1, 3) WHERE $attemptColumn = ?");
                    $update->execute([(int) $userRecord['id']]);
                    if ((int) $userRecord['FailedLoginAttempts'] >= 2) {
                        respond(['ok' => false, 'error' => 'Your account has been disabled due to multiple failed login attempts. Please contact the administrator to have your account enabled.'], 403);
                    }
                }
                respond(['ok' => false, 'error' => 'Invalid email or password.'], 401);
            }

            $pdo->prepare("UPDATE $accountTable SET FailedLoginAttempts = 0 WHERE $attemptColumn = ?")->execute([(int) $userRecord['id']]);

            $roles = [$role];
            $ids = [$role => (int) $userRecord['id']];
            if ($role === 'customer') {
                $coordinator = $pdo->prepare("SELECT CoordID FROM GARDEN_COORDINATOR WHERE GardenerID = ? AND COALESCE(NULLIF(Status, ''), 'Active') = 'Active'");
                $coordinator->execute([(int) $userRecord['id']]);
                $coordinatorId = $coordinator->fetchColumn();
                if ($coordinatorId !== false) {
                    $roles[] = 'staff';
                    $ids['staff'] = (int) $coordinatorId;
                }
            }

            $_SESSION['user'] = [
                'role' => $role,
                'id' => (int) $userRecord['id'],
                'name' => $userRecord['Name'],
                'roles' => $roles,
                'ids' => $ids,
            ];

            // If checked, save the email. If unchecked, delete the cookie.
            if (($_POST['remember'] ?? '0') === '1') {
                setcookie('remembered_email', $email, time() + (86400 * 30), '/');
            } else {
                setcookie('remembered_email', '', time() - 3600, '/');
            }

            respond(['ok' => true, 'redirect' => loginRedirectFor($role)]);
        }

        case 'logout':
            // Destroy the remember me cookie by setting its expiration to the past
            setcookie('remember_me', '', time() - 3600, '/');
            
            $_SESSION = [];
            session_destroy();
            respond(['ok' => true, 'redirect' => 'login.php']);

        case 'signup_request': {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $age = $_POST['age'] ?? '';
            $location = trim($_POST['location'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            $errors = [];
            
            if ($firstName === '' || mb_strlen($firstName) > 60) {
                $errors[] = 'First name is required.';
            } elseif (!preg_match("/^[A-Za-z\s\-']+$/u", $firstName)) {
                $errors[] = 'First name must contain only letters.';
            }

            if ($lastName === '' || mb_strlen($lastName) > 60) {
                $errors[] = 'Last name is required.';
            } elseif (!preg_match("/^[A-Za-z\s\-']+$/u", $lastName)) {
                $errors[] = 'Last name must contain only letters.';
            }

            if (!ctype_digit((string) $age) || (int) $age < 18 || (int) $age > 120) {
                $errors[] = 'You must be at least 18 years old to register.';
            }

            if (!in_array($location, NCR_CITIES, true)) $errors[] = 'Please choose a valid NCR city.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
            // Enforce password complexity: 8+ chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
            if (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}$/', $password)) {
                respond(['ok' => false, 'error' => 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.'], 422);
            }
            if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';
            if (($_POST['accept_terms'] ?? '') !== '1') $errors[] = 'You must agree to the Terms of Service.';
            
            if ($errors) respond(['ok' => false, 'errors' => $errors], 422);

            // An email already active as any account, or already sitting
            // in the queue as a pending request, can't submit another one.
            $inUse = $pdo->prepare("
                SELECT 1 FROM COMMUNITY_GARDENER WHERE Email = ?
                UNION SELECT 1 FROM GARDEN_COORDINATOR WHERE Email = ?
                UNION SELECT 1 FROM SYSTEM_ADMINISTRATOR WHERE Email = ?
                UNION SELECT 1 FROM SIGNUP_REQUEST WHERE Email = ? AND Status = 'Pending'
            ");
            $inUse->execute([$email, $email, $email, $email]);
            if ($inUse->fetchColumn()) {
                respond(['ok' => false, 'error' => 'That email already has an account or a pending request.'], 409);
            }

            $statusToken = bin2hex(random_bytes(32));
            $pdo->prepare("
                INSERT INTO SIGNUP_REQUEST (FirstName, LastName, Age, Location, Email, PasswordHash, Role, Shift, StatusToken)
                VALUES (?, ?, ?, ?, ?, ?, 'customer', 'Morning', ?)
            ")->execute([
                htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8'),
                (int) $age,
                htmlspecialchars($location, ENT_QUOTES, 'UTF-8'),
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                hash('sha256', $statusToken),
            ]);
            respond(['ok' => true, 'status_token' => $statusToken]);
        }

        case 'application_status': {
            $token = trim($_GET['token'] ?? '');
            if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
                respond(['ok' => false, 'error' => 'Invalid status link.'], 422);
            }
            $stmt = $pdo->prepare('SELECT Status, RejectionReason FROM SIGNUP_REQUEST WHERE StatusToken = ?');
            $stmt->execute([hash('sha256', $token)]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$request) respond(['ok' => false, 'error' => 'This request status link is invalid or expired.'], 404);
            respond(['ok' => true, 'status' => $request['Status'], 'reason' => $request['RejectionReason']]);
        }

        // ---------------- PASSWORD RESET ----------------

        case 'forgot_password': {
            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respond(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
            }

            // 1. Verify the email exists in ANY of our user tables
            $stmt = $pdo->prepare("
                SELECT Email FROM COMMUNITY_GARDENER WHERE Email = ?
                UNION SELECT Email FROM GARDEN_COORDINATOR WHERE Email = ?
                UNION SELECT Email FROM SYSTEM_ADMINISTRATOR WHERE Email = ?
            ");
            $stmt->execute([$email, $email, $email]);
            
            // Temporarily throw an error so we can debug
            if (!$stmt->fetchColumn()) {
                respond(['ok' => false, 'error' => 'Not found in database!'], 404); 
            }

            // 2. Generate a secure random token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiration

            // 3. Store the hashed token (Upsert so old tokens are overwritten)
            $pdo->prepare("
                INSERT INTO PASSWORD_RESET (Email, TokenHash, ExpiresAt) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE TokenHash = VALUES(TokenHash), ExpiresAt = VALUES(ExpiresAt)
            ")->execute([$email, $tokenHash, $expiresAt]);

            // 4. Send the email using a cURL helper function
            // Make sure to change 'localhost...' to your actual domain when deploying
            // Automatically detect the current folder path so the link works anywhere
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $baseDir = dirname($_SERVER['REQUEST_URI']);
            $resetLink = $protocol . $_SERVER['HTTP_HOST'] . $baseDir . "/reset_password.php?email=" . urlencode($email) . "&token=" . $token;
            
            sendResetEmail($email, $resetLink);

            respond(['ok' => true]);
        }

        case 'reset_password': {
            $email = trim($_POST['email'] ?? '');
            $token = $_POST['token'] ?? '';
            $newPassword = $_POST['password'] ?? '';

            if (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}$/', $newPassword)) {
                respond(['ok' => false, 'error' => 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.'], 422);
            }

            // 1. Verify the token
            $tokenHash = hash('sha256', $token);
            $stmt = $pdo->prepare("SELECT ExpiresAt FROM PASSWORD_RESET WHERE Email = ? AND TokenHash = ?");
            $stmt->execute([$email, $tokenHash]);
            $expiresAt = $stmt->fetchColumn();

            if (!$expiresAt || strtotime($expiresAt) < time()) {
                respond(['ok' => false, 'error' => 'Invalid or expired reset link. Please request a new one.'], 403);
            }

            // 2. Update the password in whichever table the user belongs to
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $pdo->prepare("UPDATE COMMUNITY_GARDENER SET PasswordHash = ? WHERE Email = ?")->execute([$hashedPassword, $email]);
            $pdo->prepare("UPDATE GARDEN_COORDINATOR SET PasswordHash = ? WHERE Email = ?")->execute([$hashedPassword, $email]);
            $pdo->prepare("UPDATE SYSTEM_ADMINISTRATOR SET PasswordHash = ? WHERE Email = ?")->execute([$hashedPassword, $email]);

            // 3. Delete the used token
            $pdo->prepare("DELETE FROM PASSWORD_RESET WHERE Email = ?")->execute([$email]);

            respond(['ok' => true]);
        }

        // ---------------- CUSTOMER: Exchange Board ----------------

        case 'list': {
            $search = trim($_GET['search'] ?? '');
            $minQty = isset($_GET['min_qty']) && $_GET['min_qty'] !== '' ? (int) $_GET['min_qty'] : null;
            $sortKey = $_GET['sort'] ?? 'newest';
            $orderBy = SORT_OPTIONS[$sortKey] ?? SORT_OPTIONS['newest'];

            $sql = "
                SELECT L.ListingID, L.Crop, L.Qty, L.Notes, L.CreatedAt, G.Name AS GardenerName
                FROM EXCHANGE_LISTING L
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = L.GardenerID
                WHERE L.ListingID NOT IN (SELECT ListingID FROM EXCHANGE_ORDER)
            ";
            $params = [];
            if ($search !== '') { $sql .= " AND L.Crop LIKE :search"; $params[':search'] = '%' . $search . '%'; }
            if ($minQty !== null) { $sql .= " AND L.Qty >= :min_qty"; $params[':min_qty'] = $minQty; }
            $sql .= " ORDER BY {$orderBy}";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            respond(['ok' => true, 'listings' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'create': {
            $user = requireJsonRole('customer');
            $crop = trim($_POST['crop'] ?? '');
            $qty = $_POST['qty'] ?? '';
            $notes = trim($_POST['notes'] ?? '');

            $errors = [];
            if ($crop === '' || mb_strlen($crop) > 60) {
                $errors[] = 'Crop name is required (max 60 characters).';
            } elseif (!preg_match("/^[A-Za-z\s\-']+$/u", $crop)) {
                $errors[] = 'Crop name may only contain letters, spaces, and hyphens.';
            }
            if (!ctype_digit((string) $qty) || (int) $qty < 1 || (int) $qty > 1000) $errors[] = 'Quantity must be between 1 and 1000.';
            if (mb_strlen($notes) > 200) $errors[] = 'Notes must be 200 characters or fewer.';
            if ($errors) respond(['ok' => false, 'errors' => $errors], 422);

            $stmt = $pdo->prepare("INSERT INTO EXCHANGE_LISTING (GardenerID, Crop, Qty, Notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], htmlspecialchars($crop, ENT_QUOTES, 'UTF-8'), (int) $qty, htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')]);
            respond(['ok' => true, 'listing_id' => $pdo->lastInsertId()]);
        }

        case 'claim': {
            $user = requireJsonRole('customer');
            $listingId = $_POST['listing_id'] ?? '';
            if (!ctype_digit((string) $listingId)) respond(['ok' => false, 'error' => 'Invalid listing id.'], 422);

            $check = $pdo->prepare("
                SELECT GardenerID FROM EXCHANGE_LISTING
                WHERE ListingID = ? AND ListingID NOT IN (SELECT ListingID FROM EXCHANGE_ORDER)
            ");
            $check->execute([(int) $listingId]);
            $owner = $check->fetchColumn();

            if ($owner === false) respond(['ok' => false, 'error' => 'Listing not found or already claimed.'], 404);
            if ((int) $owner === $user['id']) respond(['ok' => false, 'error' => "You can't claim your own listing."], 403);

            $pdo->prepare("INSERT INTO EXCHANGE_ORDER (ListingID, GardenerID) VALUES (?, ?)")
                ->execute([(int) $listingId, $user['id']]);
            respond(['ok' => true]);
        }

        // ---------------- CUSTOMER: Plot ----------------

        case 'my_plot': {
            $user = requireJsonRole('customer');
            $plot = $pdo->prepare("SELECT PltID, Label, Status FROM PLOT WHERE GardenerID = ? ORDER BY Label");
            $plot->execute([$user['id']]);
            $plots = $plot->fetchAll(PDO::FETCH_ASSOC);

            $pending = $pdo->prepare("
                SELECT PA.AppID, P.Label, PA.RequestType FROM PLOT_APPLICATION PA
                JOIN PLOT P ON P.PltID = PA.PltID
                WHERE PA.GardenerID = ? AND PA.Status = 'Pending'
            ");
            $pending->execute([$user['id']]);

            $available = $pdo->query("SELECT PltID, Label FROM PLOT WHERE Status = 'Available'")->fetchAll(PDO::FETCH_ASSOC);

            respond([
                'ok' => true,
                'plots' => $plots,
                'pending_application' => $pending->fetch(PDO::FETCH_ASSOC) ?: null,
                'available_plots' => $available,
            ]);
        }

        case 'request_plot_unassignment': {
            $user = requireJsonRole('customer');
            $plotId = $_POST['plt_id'] ?? '';
            if (!ctype_digit((string) $plotId)) {
                respond(['ok' => false, 'error' => 'Invalid plot.'], 422);
            }

            $pdo->beginTransaction();
            $plot = $pdo->prepare("SELECT PltID, Label FROM PLOT WHERE PltID = ? AND GardenerID = ? AND Status = 'Occupied' FOR UPDATE");
            $plot->execute([(int) $plotId, $user['id']]);
            $plotRow = $plot->fetch(PDO::FETCH_ASSOC);
            if (!$plotRow) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'That plot is not assigned to you.'], 409);
            }

            $pending = $pdo->prepare("SELECT 1 FROM PLOT_APPLICATION WHERE GardenerID = ? AND PltID = ? AND Status = 'Pending' FOR UPDATE");
            $pending->execute([$user['id'], (int) $plotId]);
            if ($pending->fetchColumn()) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'An unassignment request is already pending.'], 409);
            }

            $pdo->prepare("INSERT INTO PLOT_APPLICATION (GardenerID, PltID, Status, RequestType) VALUES (?, ?, 'Pending', 'Unassign')")
                ->execute([$user['id'], (int) $plotId]);
            recordPlotEvent($pdo, 'Request Unassign', 'customer', $user['name'], $plotRow['Label'], (int) $plotId, (int) $user['id'], $user['name'], null, (int) $pdo->lastInsertId());
            $pdo->commit();
            respond(['ok' => true]);
        }

        case 'apply_plot': {
            $user = requireJsonRole('customer');
            $pltId = $_POST['plt_id'] ?? '';
            if (!ctype_digit((string) $pltId)) respond(['ok' => false, 'error' => 'Invalid plot.'], 422);

            $pdo->beginTransaction();
            try {
                $plot = $pdo->prepare("SELECT Label, Status, GardenerID FROM PLOT WHERE PltID = ? FOR UPDATE");
                $plot->execute([(int) $pltId]);
                $row = $plot->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['Status'] !== 'Available' || $row['GardenerID'] !== null) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'That plot is no longer available.'], 409);
                }

                $existing = $pdo->prepare("SELECT 1 FROM PLOT_APPLICATION WHERE GardenerID = ? AND PltID = ? AND Status = 'Pending' AND RequestType = 'Apply' LIMIT 1");
                $existing->execute([$user['id'], (int) $pltId]);
                if ($existing->fetchColumn()) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'You already requested this plot.'], 409);
                }

                $pdo->prepare("INSERT INTO PLOT_APPLICATION (GardenerID, PltID, Status, RequestType) VALUES (?, ?, 'Pending', 'Apply')")
                    ->execute([$user['id'], (int) $pltId]);
                $appId = (int) $pdo->lastInsertId();
                recordPlotEvent($pdo, 'Request Assignment', 'customer', $user['name'], $row['Label'], (int) $pltId, (int) $user['id'], $user['name'], null, $appId);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            respond(['ok' => true]);
        }

        // ---------------- CUSTOMER: Crop Log ----------------

        case 'my_croplog': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("
                SELECT L.LogID, L.CropName, L.MaintenanceNotes, L.HarvestYield, L.LoggedAt,
                       L.GardenPlotID, GP.PlantedDate AS GardenPlantedDate, P.Label
                  FROM CROP_LOG L LEFT JOIN PLOT P ON P.PltID = L.PltID
                LEFT JOIN GARDEN_PLOTS GP ON GP.PlotID = L.GardenPlotID
                WHERE L.GardenerID = ? ORDER BY L.LoggedAt DESC
            ");
            $stmt->execute([$user['id']]);
            respond(['ok' => true, 'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'croplog_create': {
            $user = requireJsonRole('customer');
            $gardenPlotId = $_POST['garden_plot_id'] ?? '';
            $notes = trim($_POST['notes'] ?? '');
            $yield = trim($_POST['yield'] ?? '');

            if (!ctype_digit((string) $gardenPlotId)) respond(['ok' => false, 'error' => 'Select a crop from your garden log.'], 422);
            if (mb_strlen($notes) > 300 || mb_strlen($yield) > 60) respond(['ok' => false, 'error' => 'Notes or yield too long.'], 422);

            $cropStmt = $pdo->prepare("SELECT CropName FROM GARDEN_PLOTS WHERE PlotID = ? AND GardenerID = ? AND Status NOT IN ('Harvested', 'Failed')");
            $cropStmt->execute([(int) $gardenPlotId, $user['id']]);
            $crop = $cropStmt->fetchColumn();
            if ($crop === false) respond(['ok' => false, 'error' => 'That crop is not in your garden log.'], 422);
            if (mb_strlen($crop) > 60) respond(['ok' => false, 'error' => 'This crop name is too long for a maintenance entry.'], 422);

            $plotStmt = $pdo->prepare("SELECT PltID FROM PLOT WHERE GardenerID = ? AND Status = 'Occupied' ORDER BY PltID LIMIT 1");
            $plotStmt->execute([$user['id']]);
            $pltId = $plotStmt->fetchColumn();
            $pltId = $pltId === false ? null : (int) $pltId;

            $pdo->prepare("INSERT INTO CROP_LOG (GardenerID, GardenPlotID, PltID, CropName, MaintenanceNotes, HarvestYield) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$user['id'], (int) $gardenPlotId, $pltId, htmlspecialchars($crop, ENT_QUOTES, 'UTF-8'), htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'), htmlspecialchars($yield, ENT_QUOTES, 'UTF-8')]);
            respond(['ok' => true]);
        }

        case 'garden_journal_update': {
            $user = requireJsonRole('customer');
            foreach (['plot_id', 'status', 'logged_date', 'notes', 'yield'] as $field) {
                if (isset($_POST[$field]) && !is_string($_POST[$field])) {
                    respond(['ok' => false, 'error' => 'Journal fields must be submitted as text values.'], 422);
                }
            }

            $plotId = $_POST['plot_id'] ?? '';
            $status = trim($_POST['status'] ?? '');
            $loggedDate = trim($_POST['logged_date'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $yield = trim($_POST['yield'] ?? '');
            $validStatuses = ['Planted', 'Growing', 'Harvested', 'Failed'];

            if (!ctype_digit((string) $plotId) || (int) $plotId < 1) {
                respond(['ok' => false, 'error' => 'Choose a valid crop from your garden journal.'], 422);
            }
            if (!in_array($status, $validStatuses, true)) {
                respond(['ok' => false, 'error' => 'Choose a valid crop status.'], 422);
            }
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $loggedDate);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $loggedDate) {
                respond(['ok' => false, 'error' => 'Choose a valid journal date.'], 422);
            }
            if (mb_strlen($notes) > 1000 || mb_strlen($yield) > 60) {
                respond(['ok' => false, 'error' => 'Maintenance notes or yield exceed the allowed length.'], 422);
            }

            $pdo->beginTransaction();
            try {
                $cropStmt = $pdo->prepare("
                    SELECT PlotID, CropName, Status
                    FROM GARDEN_PLOTS
                    WHERE PlotID = ? AND GardenerID = ?
                    FOR UPDATE
                ");
                $cropStmt->execute([(int) $plotId, $user['id']]);
                $crop = $cropStmt->fetch(PDO::FETCH_ASSOC);
                if (!$crop) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Crop not found in your garden journal.'], 404);
                }
                if (mb_strlen($crop['CropName']) > 60) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'This crop name is too long to record in the journal.'], 422);
                }

                $statusChanged = $crop['Status'] !== $status;
                if (!$statusChanged && $notes === '' && $yield === '') {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Add a maintenance note, yield, or status change before saving.'], 422);
                }

                if ($statusChanged) {
                    $pdo->prepare("UPDATE GARDEN_PLOTS SET Status = ? WHERE PlotID = ? AND GardenerID = ?")
                        ->execute([$status, (int) $plotId, $user['id']]);
                }

                $plotStmt = $pdo->prepare("
                    SELECT PltID FROM PLOT
                    WHERE GardenerID = ? AND Status = 'Occupied'
                    ORDER BY PltID LIMIT 1
                ");
                $plotStmt->execute([$user['id']]);
                $communityPlotId = $plotStmt->fetchColumn();
                $communityPlotId = $communityPlotId === false ? null : (int) $communityPlotId;

                $logNotes = $notes;
                if ($statusChanged) {
                    $statusNote = "Crop status changed from {$crop['Status']} to {$status}.";
                    $logNotes = $logNotes === '' ? $statusNote : $statusNote . "\n" . $logNotes;
                }

                $pdo->prepare("
                    INSERT INTO CROP_LOG
                        (GardenerID, GardenPlotID, PltID, CropName, MaintenanceNotes, HarvestYield, LoggedAt)
                    VALUES (?, ?, ?, ?, ?, ?, CONCAT(?, ' ', TIME(CURRENT_TIMESTAMP)))
                ")->execute([
                    $user['id'],
                    (int) $plotId,
                    $communityPlotId,
                    $crop['CropName'],
                    $logNotes === '' ? null : $logNotes,
                    $yield === '' ? null : $yield,
                    $loggedDate,
                ]);

                $pdo->commit();
                respond(['ok' => true, 'status' => $status, 'status_changed' => $statusChanged]);
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        }

        // ---------------------------------------------------------
        // Exchange Board Endpoints
        // ---------------------------------------------------------

        case 'exchange_feed': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->query("SELECT * FROM EXCHANGE_BOARD WHERE Status = 'Active' ORDER BY CreatedAt DESC");
            respond([
                'ok' => true, 
                'current_user_id' => $user['id'], // Added so JS knows who is logged in
                'posts' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
        }

        case 'my_exchange_listings': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("SELECT * FROM EXCHANGE_BOARD WHERE GardenerID = ? AND Status = 'Active' ORDER BY CreatedAt DESC");
            $stmt->execute([$user['id']]);
            respond(['ok' => true, 'posts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'add_exchange_post': {
            $user = requireJsonRole('customer');
            $gardenPlotId = $_POST['garden_plot_id'] ?? '';
            $qty = trim($_POST['qty'] ?? '');
            $desc = trim($_POST['desc'] ?? '');

            if (!ctype_digit((string) $gardenPlotId)) respond(['ok' => false, 'error' => 'Select a harvested crop.'], 422);
            if ($qty === '') respond(['ok' => false, 'error' => 'Quantity is required.'], 422);

            $pdo->beginTransaction();
            try {
                $cropStmt = $pdo->prepare("SELECT CropName, Status FROM GARDEN_PLOTS WHERE PlotID = ? AND GardenerID = ? FOR UPDATE");
                $cropStmt->execute([(int) $gardenPlotId, $user['id']]);
                $crop = $cropStmt->fetch(PDO::FETCH_ASSOC);
                if (!$crop || $crop['Status'] !== 'Harvested') {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Only crops marked Harvested can be posted.'], 422);
                }

                $stmt = $pdo->prepare("INSERT INTO EXCHANGE_BOARD (GardenerID, ProduceName, Qty, Description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user['id'], $crop['CropName'], $qty, $desc]);
                $postId = (int) $pdo->lastInsertId();
                $pdo->commit();
                respond(['ok' => true, 'post_id' => $postId]);
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        }

        case 'close_exchange_post': {
            $user = requireJsonRole('customer');
            $postId = (int)($_POST['post_id'] ?? 0);

            $stmt = $pdo->prepare("UPDATE EXCHANGE_BOARD SET Status = 'Completed' WHERE PostID = ? AND GardenerID = ?");
            $stmt->execute([$postId, $user['id']]);
            respond(['ok' => true]);
        }

        // ---------------------------------------------------------
        // Claim Management Endpoints
        // ---------------------------------------------------------

        case 'send_claim_request': {
            $user = requireJsonRole('customer');
            $postId = (int)($_POST['post_id'] ?? 0);
            $qty = trim($_POST['qty'] ?? '');
            $pickup = trim($_POST['pickup'] ?? '');

            if (!$postId || empty($qty) || empty($pickup)) {
                respond(['ok' => false, 'error' => 'All fields are required.'], 422);
            }
            
            $stmt = $pdo->prepare("INSERT INTO EXCHANGE_CLAIMS (PostID, RequesterID, QtyWanted, PickupDetails) VALUES (?, ?, ?, ?)");
            $stmt->execute([$postId, $user['id'], $qty, $pickup]);
            respond(['ok' => true]);
        }

        case 'my_pending_claims': {
            $user = requireJsonRole('customer');
            // Fetch claims made by others on posts owned by the logged-in user
            $stmt = $pdo->prepare("
                SELECT c.ClaimID, c.QtyWanted, c.PickupDetails, c.CreatedAt, b.ProduceName 
                FROM EXCHANGE_CLAIMS c 
                JOIN EXCHANGE_BOARD b ON c.PostID = b.PostID 
                WHERE b.GardenerID = ? AND c.Status = 'Pending'
                ORDER BY c.CreatedAt ASC
            ");
            $stmt->execute([$user['id']]);
            respond(['ok' => true, 'claims' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'handle_claim_request': {
            $user = requireJsonRole('customer');
            $claimId = (int)($_POST['claim_id'] ?? 0);
            $status = $_POST['status'] ?? ''; // 'Accepted' or 'Rejected'

            if (!in_array($status, ['Accepted', 'Rejected'])) {
                respond(['ok' => false, 'error' => 'Invalid action.'], 422);
            }

            // 1. Fetch the claim and board data to verify ownership and check quantities
            $stmt = $pdo->prepare("
                SELECT c.QtyWanted, b.Qty as CurrentQty, b.PostID 
                FROM EXCHANGE_CLAIMS c 
                JOIN EXCHANGE_BOARD b ON c.PostID = b.PostID 
                WHERE c.ClaimID = ? AND b.GardenerID = ?
            ");
            $stmt->execute([$claimId, $user['id']]);
            $postData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$postData) {
                respond(['ok' => false, 'error' => 'Claim not found or access denied.'], 403);
            }

            // 2. If accepted, do the math to reduce the quantity
            if ($status === 'Accepted') {
                // Extract numbers from strings (e.g., pulls "2" from "2 pcs" and "5" from "5 pcs")
                preg_match('/[0-9]+(\.[0-9]+)?/', $postData['QtyWanted'], $reqMatches);
                preg_match('/[0-9]+(\.[0-9]+)?/', $postData['CurrentQty'], $availMatches);
                
                $reqNum = isset($reqMatches[0]) ? (float)$reqMatches[0] : 0;
                $availNum = isset($availMatches[0]) ? (float)$availMatches[0] : 0;
                
                // Subtract to get new quantity, ensuring it doesn't drop below 0
                $newNum = max(0, $availNum - $reqNum);
                
                // Inject the new number back into the original string format (e.g., "3 pcs")
                $newQtyStr = preg_replace('/[0-9]+(\.[0-9]+)?/', $newNum, $postData['CurrentQty'], 1);

                // Update the board's quantity
                $updateBoard = $pdo->prepare("UPDATE EXCHANGE_BOARD SET Qty = ? WHERE PostID = ?");
                $updateBoard->execute([$newQtyStr, $postData['PostID']]);

                // Auto-close the post if quantity reaches 0
                if ($newNum <= 0) {
                    $pdo->prepare("UPDATE EXCHANGE_BOARD SET Status = 'Completed' WHERE PostID = ?")->execute([$postData['PostID']]);
                }
            }

            // 3. Finally, update the claim's status
            $stmt = $pdo->prepare("UPDATE EXCHANGE_CLAIMS SET Status = ? WHERE ClaimID = ?");
            $stmt->execute([$status, $claimId]);
            
            respond(['ok' => true]);
        }
        // ---------------- CUSTOMER: Resources ----------------

        case 'resources': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("
                SELECT R.ResourceID, R.Name, R.TotalQty,
                       GREATEST(0, R.TotalQty - COALESCE((
                           SELECT SUM(T.Qty) FROM RESOURCE_TXN T
                           WHERE T.ResourceID = R.ResourceID AND T.Status IN ('Approved', 'Return Requested')
                       ), 0)) AS AvailableQty,
                       COALESCE((
                           SELECT SUM(T.Qty) FROM RESOURCE_TXN T
                           WHERE T.ResourceID = R.ResourceID AND T.GardenerID = ? AND T.Status = 'Requested' AND T.RequestType = 'Borrow'
                       ), 0) AS MyPendingQty
                FROM RESOURCE R ORDER BY R.Name
            ");
            $stmt->execute([$user['id']]);
            $pending = $pdo->prepare("SELECT COUNT(*) FROM RESOURCE_TXN WHERE GardenerID = ? AND Status = 'Requested' AND RequestType = 'Borrow'");
            $pending->execute([$user['id']]);
            respond([
                'ok' => true,
                'resources' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'my_pending_count' => (int) $pending->fetchColumn(),
                'max_pending' => MAX_PENDING_RESOURCE_REQUESTS,
            ]);
        }

        case 'resource_request': {
            $user = requireJsonRole('customer');
            $resourceId = $_POST['resource_id'] ?? '';
            $qty = $_POST['qty'] ?? '';
            if (!ctype_digit((string) $resourceId) || !ctype_digit((string) $qty) || (int) $qty < 1) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $pdo->beginTransaction();

            // Lock the gardener's row so two requests sent at the same moment
            // are counted one after the other against the limits below.
            $pdo->prepare('SELECT GardenerID FROM COMMUNITY_GARDENER WHERE GardenerID = ? FOR UPDATE')
                ->execute([$user['id']]);

            $pendingCount = $pdo->prepare("SELECT COUNT(*) FROM RESOURCE_TXN WHERE GardenerID = ? AND Status = 'Requested' AND RequestType = 'Borrow'");
            $pendingCount->execute([$user['id']]);
            if ((int) $pendingCount->fetchColumn() >= MAX_PENDING_RESOURCE_REQUESTS) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'You already have ' . MAX_PENDING_RESOURCE_REQUESTS . ' pending requests. Cancel one or wait for a coordinator to review them before requesting more.'], 429);
            }

            $recentCount = $pdo->prepare("SELECT COUNT(*) FROM RESOURCE_TXN WHERE GardenerID = ? AND RequestType = 'Borrow' AND Status IN ('Requested', 'Cancelled') AND RequestedAt >= NOW() - INTERVAL " . RESOURCE_REQUEST_RATE_WINDOW_MINUTES . " MINUTE");
            $recentCount->execute([$user['id']]);
            if ((int) $recentCount->fetchColumn() >= RESOURCE_REQUEST_RATE_LIMIT) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'You have sent a lot of requests in a short time. Please wait a few minutes and try again.'], 429);
            }

            $res = $pdo->prepare("
                SELECT GREATEST(0, R.TotalQty - COALESCE((
                    SELECT SUM(T.Qty) FROM RESOURCE_TXN T
                    WHERE T.ResourceID = R.ResourceID AND T.Status IN ('Approved', 'Return Requested')
                ), 0)) AS AvailableQty
                FROM RESOURCE R WHERE R.ResourceID = ? FOR UPDATE
            ");
            $res->execute([(int) $resourceId]);
            $available = $res->fetchColumn();
            if ($available === false || (int) $qty > (int) $available) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'Not enough of that resource available.'], 409);
            }

            // Only one pending request per resource: the gardener must cancel the
            // existing one before asking for a different quantity.
            $existing = $pdo->prepare("SELECT TxnID FROM RESOURCE_TXN WHERE GardenerID = ? AND ResourceID = ? AND Status = 'Requested' AND RequestType = 'Borrow' FOR UPDATE");
            $existing->execute([$user['id'], (int) $resourceId]);
            if ($existing->fetchColumn()) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'You already have a pending request for this item. Cancel it first if you need a different quantity.'], 409);
            }

            $pdo->prepare("INSERT INTO RESOURCE_TXN (GardenerID, ResourceID, Qty, Status) VALUES (?, ?, ?, 'Requested')")
                ->execute([$user['id'], (int) $resourceId, (int) $qty]);
            $pdo->commit();
            respond(['ok' => true]);
        }

        case 'cancel_resource_request': {
            $user = requireJsonRole('customer');
            $txnId = $_POST['txn_id'] ?? '';
            if (!ctype_digit((string) $txnId)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $stmt = $pdo->prepare("UPDATE RESOURCE_TXN SET Status = 'Cancelled' WHERE TxnID = ? AND GardenerID = ? AND Status = 'Requested'");
            $stmt->execute([(int) $txnId, $user['id']]);
            if ($stmt->rowCount() === 0) {
                respond(['ok' => false, 'error' => 'This request can no longer be cancelled. It may have already been processed.'], 409);
            }
            respond(['ok' => true]);
        }

        case 'my_resource_requests': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("
                SELECT T.TxnID, R.Name, T.Qty, T.Status, T.RequestType, T.RequestNotes,
                       T.SourcePersonalItemID, T.ProcessedQty, T.RejectionReason,
                       T.RequestedAt, T.ApprovedAt, T.ReturnRequestedAt
                FROM RESOURCE_TXN T JOIN RESOURCE R ON R.ResourceID = T.ResourceID
                WHERE T.GardenerID = ? ORDER BY T.RequestedAt DESC
            ");
            $stmt->execute([$user['id']]);
            respond(['ok' => true, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        
        case 'return_resource': {
            $user = requireJsonRole('customer');
            $txnId = $_POST['txn_id'] ?? '';

            if (!ctype_digit((string) $txnId)) {
                respond(['ok' => false, 'error' => 'Invalid transaction.'], 422);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT ResourceID, PltID, Qty, Status FROM RESOURCE_TXN WHERE TxnID = ? AND GardenerID = ? FOR UPDATE");
            $stmt->execute([(int) $txnId, $user['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !in_array($row['Status'], ['Approved', 'Return Requested'], true)) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'Could not return this item.'], 400);
            }

            $resource = $pdo->prepare('SELECT TotalQty FROM RESOURCE WHERE ResourceID = ? FOR UPDATE');
            $resource->execute([(int) $row['ResourceID']]);
            $totalQty = $resource->fetchColumn();
            if ($totalQty === false) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'Resource not found.'], 404);
            }

            $plotLabel = null;
            if ($row['PltID'] !== null) {
                $plot = $pdo->prepare('SELECT Label FROM PLOT WHERE PltID = ?');
                $plot->execute([(int) $row['PltID']]);
                $plotLabel = $plot->fetchColumn() ?: null;
            }
            recordResourceEvent($pdo, (int) $row['ResourceID'], 'Returned', (int) $row['Qty'], 'customer', $user['name'], (int) $user['id'], $user['name'], null, $row['PltID'] === null ? null : (int) $row['PltID'], $plotLabel);

            $pdo->prepare("UPDATE RESOURCE_TXN SET Status = 'Returned', ReturnedAt = NOW() WHERE TxnID = ?")
                ->execute([(int) $txnId]);
            $active = $pdo->prepare("SELECT COALESCE(SUM(Qty), 0) FROM RESOURCE_TXN WHERE ResourceID = ? AND Status IN ('Approved', 'Return Requested')");
            $active->execute([(int) $row['ResourceID']]);
            $availableQty = max(0, (int) $totalQty - (int) $active->fetchColumn());
            $pdo->prepare('UPDATE RESOURCE SET AvailableQty = ? WHERE ResourceID = ?')
                ->execute([$availableQty, (int) $row['ResourceID']]);
            $pdo->commit();
            respond(['ok' => true]);
        }

        // ---------------------------------------------------------
        // Personal Inventory Endpoints
        // ---------------------------------------------------------
        
        case 'get_personal_inventory': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("
                SELECT I.ItemID, I.ItemName, I.Qty, I.AddedAt,
                       EXISTS (
                           SELECT 1 FROM RESOURCE_TXN T
                           WHERE T.SourcePersonalItemID = I.ItemID
                             AND T.Status = 'Requested' AND T.RequestType = 'Donation'
                       ) AS HasPendingDonation
                FROM PERSONAL_INVENTORY I
                WHERE I.GardenerID = ? ORDER BY I.AddedAt DESC
            ");
            $stmt->execute([$user['id']]);
            respond(['ok' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'request_resource_donation': {
            $user = requireJsonRole('customer');
            $name = trim($_POST['item_name'] ?? '');
            $qtyRaw = $_POST['qty'] ?? '';
            $notes = trim($_POST['notes'] ?? '');
            $sourceItemId = $_POST['source_item_id'] ?? '';

            if (!ctype_digit((string) $qtyRaw) || (int) $qtyRaw < 1 || (int) $qtyRaw > 100000) {
                respond(['ok' => false, 'error' => 'Enter a quantity from 1 to 100,000.'], 422);
            }
            if (mb_strlen($notes) > 1000) {
                respond(['ok' => false, 'error' => 'Donation notes cannot exceed 1,000 characters.'], 422);
            }
            if ($sourceItemId !== '' && !ctype_digit((string) $sourceItemId)) {
                respond(['ok' => false, 'error' => 'Invalid personal inventory item.'], 422);
            }

            $qty = (int) $qtyRaw;
            $sourceItemId = $sourceItemId === '' ? null : (int) $sourceItemId;
            $pdo->beginTransaction();
            try {
                if ($sourceItemId !== null) {
                    $itemStmt = $pdo->prepare('SELECT ItemName, Qty FROM PERSONAL_INVENTORY WHERE ItemID = ? AND GardenerID = ? FOR UPDATE');
                    $itemStmt->execute([$sourceItemId, $user['id']]);
                    $personalItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$personalItem || $qty > (int) $personalItem['Qty']) {
                        $pdo->rollBack();
                        respond(['ok' => false, 'error' => 'The donation quantity exceeds the item in your inventory.'], 422);
                    }
                    $pendingStmt = $pdo->prepare("SELECT 1 FROM RESOURCE_TXN WHERE SourcePersonalItemID = ? AND Status = 'Requested' AND RequestType = 'Donation' LIMIT 1");
                    $pendingStmt->execute([$sourceItemId]);
                    if ($pendingStmt->fetchColumn()) {
                        $pdo->rollBack();
                        respond(['ok' => false, 'error' => 'A donation request for this item is already pending.'], 409);
                    }
                    $name = $personalItem['ItemName'];
                }
                if ($name === '' || mb_strlen($name) > 80) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Donation item names must be between 1 and 80 characters.'], 422);
                }

                $resourceStmt = $pdo->prepare('SELECT ResourceID FROM RESOURCE WHERE LOWER(Name) = LOWER(?) LIMIT 1 FOR UPDATE');
                $resourceStmt->execute([$name]);
                $resourceId = $resourceStmt->fetchColumn();
                if ($resourceId === false) {
                    $pdo->prepare('INSERT INTO RESOURCE (Name, TotalQty, AvailableQty) VALUES (?, 0, 0)')
                        ->execute([$name]);
                    $resourceId = (int) $pdo->lastInsertId();
                } else {
                    $resourceId = (int) $resourceId;
                }

                $pdo->prepare("
                    INSERT INTO RESOURCE_TXN
                        (GardenerID, ResourceID, Qty, RequestType, RequestNotes, SourcePersonalItemID, Status)
                    VALUES (?, ?, ?, 'Donation', ?, ?, 'Requested')
                ")->execute([$user['id'], $resourceId, $qty, $notes === '' ? null : $notes, $sourceItemId]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            respond(['ok' => true]);
        }

        case 'add_personal_item': {
            $user = requireJsonRole('customer');
            $name = trim($_POST['item_name'] ?? '');
            $qtyRaw = $_POST['qty'] ?? '';

            if ($name === '' || mb_strlen($name) > 100 || !ctype_digit((string) $qtyRaw) || (int) $qtyRaw < 1 || (int) $qtyRaw > 100000) {
                respond(['ok' => false, 'error' => 'Enter an item name and a quantity from 1 to 100,000.'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO PERSONAL_INVENTORY (GardenerID, ItemName, Qty) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $name, (int) $qtyRaw]);
            respond(['ok' => true]);
        }

        case 'remove_personal_item': {
            $user = requireJsonRole('customer');
            $itemId = (int)($_POST['item_id'] ?? 0);

            $pdo->beginTransaction();
            try {
                $item = $pdo->prepare('SELECT ItemID FROM PERSONAL_INVENTORY WHERE ItemID = ? AND GardenerID = ? FOR UPDATE');
                $item->execute([$itemId, $user['id']]);
                if (!$item->fetchColumn()) {
                    $pdo->commit();
                    respond(['ok' => true]);
                }
                $pending = $pdo->prepare("SELECT 1 FROM RESOURCE_TXN WHERE SourcePersonalItemID = ? AND GardenerID = ? AND Status = 'Requested' AND RequestType = 'Donation' LIMIT 1 FOR UPDATE");
                $pending->execute([$itemId, $user['id']]);
                if ($pending->fetchColumn()) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'This item has a pending donation request and cannot be removed yet.'], 409);
                }
                $pdo->prepare('DELETE FROM PERSONAL_INVENTORY WHERE ItemID = ? AND GardenerID = ?')
                    ->execute([$itemId, $user['id']]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            respond(['ok' => true]);
        }

        // ---------------------------------------------------------
        // Customer Dashboard Overview
        // ---------------------------------------------------------

        case 'customer_dashboard_overview': {
            $user = requireJsonRole('customer');

            // 1. KPI Counts
            $plotsStmt = $pdo->prepare("SELECT COUNT(*) FROM PLOT WHERE GardenerID = ? AND Status = 'Occupied'");
            $plotsStmt->execute([$user['id']]);
            $activePlots = (int)$plotsStmt->fetchColumn();

            $resourcesStmt = $pdo->prepare("SELECT COUNT(*) FROM RESOURCE_TXN WHERE GardenerID = ? AND Status = 'Requested'");
            $resourcesStmt->execute([$user['id']]);
            $pendingResources = (int)$resourcesStmt->fetchColumn();

            $listingsStmt = $pdo->prepare("SELECT COUNT(*) FROM EXCHANGE_BOARD WHERE GardenerID = ? AND Status = 'Active'");
            $listingsStmt->execute([$user['id']]);
            $myListings = (int)$listingsStmt->fetchColumn();

            // 2. Recent Maintenance (last 4 logs)
            $logsStmt = $pdo->prepare("
                SELECT L.CropName, L.MaintenanceNotes, L.HarvestYield, L.LoggedAt,
                       GP.PlantedDate AS GardenPlantedDate
                FROM CROP_LOG L
                LEFT JOIN GARDEN_PLOTS GP ON GP.PlotID = L.GardenPlotID
                WHERE L.GardenerID = ?
                ORDER BY L.LoggedAt DESC
                LIMIT 4
            ");
            $logsStmt->execute([$user['id']]);
            $recentLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. New on Exchange (last 4 active listings)
            $exchangeStmt = $pdo->prepare("
                SELECT ProduceName, Qty, Description, CreatedAt 
                FROM EXCHANGE_BOARD 
                WHERE Status = 'Active' AND GardenerID <> ?
                ORDER BY CreatedAt DESC 
                LIMIT 4
            ");
            $exchangeStmt->execute([$user['id']]);
            $recentExchange = $exchangeStmt->fetchAll(PDO::FETCH_ASSOC);

            $noticeStmt = $pdo->prepare("
                SELECT Reason, Details, CreatedAt
                FROM ACCOUNT_ARCHIVE_NOTICE
                WHERE GardenerID = ?
                ORDER BY CreatedAt DESC, NoticeID DESC
                LIMIT 1
            ");
            $noticeStmt->execute([$user['id']]);

            respond([
                'ok' => true,
                'stats' => [
                    'active_plots' => $activePlots,
                    'pending_resources' => $pendingResources,
                    'my_listings' => $myListings
                ],
                'recent_logs' => $recentLogs,
                'recent_exchange' => $recentExchange,
                'archive_notice' => $noticeStmt->fetch(PDO::FETCH_ASSOC) ?: null
            ]);
        }

        // ---------------- STAFF ----------------

        case 'pending_applications': {
            $user = requireJsonRole('staff');
            $rows = $pdo->query("
                SELECT PA.AppID, PA.GardenerID,
                       G.Name AS GardenerName,
                      P.Label,
                      CASE
                        WHEN PA.RequestType = 'Apply' THEN 'Pending Approval'
                        WHEN P.Status = 'Occupied' OR P.GardenerID IS NOT NULL THEN 'Occupied'
                        ELSE 'Available'
                      END AS PlotStatus,
                       PA.AppliedAt,
                       PA.RequestType
                FROM PLOT_APPLICATION PA
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = PA.GardenerID
                JOIN PLOT P ON P.PltID = PA.PltID
                WHERE PA.Status = 'Pending' ORDER BY PA.AppliedAt ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond([
                'ok' => true,
                'applications' => $rows,
                'current_gardener_id' => $user['ids']['customer'] ?? null,
            ]);
        }

        case 'process_application': {
            $user = requireJsonRole('staff');
            $appId = $_POST['app_id'] ?? '';
            $decision = $_POST['decision'] ?? '';
            $rejectionReason = trim($_POST['reason'] ?? '');
            if (!ctype_digit((string) $appId) || !in_array($decision, ['approve', 'reject'], true)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }
            if ($decision === 'reject' && ($rejectionReason === '' || mb_strlen($rejectionReason) > 1000)) {
                respond(['ok' => false, 'error' => 'Please provide a rejection reason of no more than 1,000 characters.'], 422);
            }

            $pdo->beginTransaction();
            try {
                $app = $pdo->prepare("SELECT PA.GardenerID, PA.PltID, PA.Status, PA.RequestType, G.Name AS GardenerName FROM PLOT_APPLICATION PA JOIN COMMUNITY_GARDENER G ON G.GardenerID = PA.GardenerID WHERE PA.AppID = ?");
                $app->execute([(int) $appId]);
                $row = $app->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['Status'] !== 'Pending') {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Application already processed.'], 409);
                }

                $plotStmt = $pdo->prepare('SELECT Label, GardenerID, Status FROM PLOT WHERE PltID = ? FOR UPDATE');
                $plotStmt->execute([(int) $row['PltID']]);
                $plot = $plotStmt->fetch(PDO::FETCH_ASSOC);
                if (!$plot) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Plot not found.'], 404);
                }

                $appLock = $pdo->prepare("SELECT PA.GardenerID, PA.PltID, PA.Status, PA.RequestType, G.Name AS GardenerName FROM PLOT_APPLICATION PA JOIN COMMUNITY_GARDENER G ON G.GardenerID = PA.GardenerID WHERE PA.AppID = ? FOR UPDATE");
                $appLock->execute([(int) $appId]);
                $row = $appLock->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['Status'] !== 'Pending') {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Application already processed.'], 409);
                }
                if (isset($user['ids']['customer']) && (int) $user['ids']['customer'] === (int) $row['GardenerID']) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'You cannot approve or reject your own plot request.'], 403);
                }

                $newStatus = $decision === 'approve' ? 'Approved' : 'Rejected';
                $pdo->prepare("UPDATE PLOT_APPLICATION SET Status = ?, CoordID = ?, ProcessedAt = NOW(), RejectionReason = ? WHERE AppID = ?")
                    ->execute([$newStatus, $user['id'], $decision === 'reject' ? $rejectionReason : null, (int) $appId]);
                $autoRejected = 0;

                if ($decision === 'approve') {
                    if ($row['RequestType'] === 'Unassign') {
                        $pdo->prepare("UPDATE PLOT SET GardenerID = NULL, Status = 'Available' WHERE PltID = ? AND GardenerID = ?")
                            ->execute([$row['PltID'], $row['GardenerID']]);
                        $pdo->prepare("UPDATE community_plots SET Status = 'Available', OccupantID = NULL WHERE PlotName = ?")
                            ->execute([$plot['Label']]);
                    } else {
                        if ($plot['Status'] !== 'Available' || $plot['GardenerID'] !== null) {
                            $pdo->rollBack();
                            respond(['ok' => false, 'error' => 'This plot was already assigned. Refresh the request list.'], 409);
                        }

                        $pdo->prepare("UPDATE PLOT SET GardenerID = ?, Status = 'Occupied' WHERE PltID = ?")
                            ->execute([$row['GardenerID'], $row['PltID']]);
                        $pdo->prepare("UPDATE community_plots SET Status = 'Occupied', OccupantID = ? WHERE PlotName = ?")
                            ->execute([$row['GardenerID'], $plot['Label']]);
                        $otherRequests = $pdo->prepare("SELECT PA.AppID, PA.GardenerID, G.Name AS GardenerName FROM PLOT_APPLICATION PA JOIN COMMUNITY_GARDENER G ON G.GardenerID = PA.GardenerID WHERE PA.PltID = ? AND PA.AppID <> ? AND PA.Status = 'Pending' AND PA.RequestType = 'Apply' FOR UPDATE");
                        $otherRequests->execute([$row['PltID'], (int) $appId]);
                        $rejectedRequests = $otherRequests->fetchAll(PDO::FETCH_ASSOC);
                        if ($rejectedRequests) {
                            $rejectOthers = $pdo->prepare("UPDATE PLOT_APPLICATION SET Status = 'Rejected', CoordID = ?, ProcessedAt = NOW(), RejectionReason = ? WHERE PltID = ? AND AppID <> ? AND Status = 'Pending' AND RequestType = 'Apply'");
                            $rejectOthers->execute([$user['id'], 'Another gardener was approved for this plot.', $row['PltID'], (int) $appId]);
                            foreach ($rejectedRequests as $rejectedRequest) {
                                recordPlotEvent($pdo, 'Request Rejected', 'staff', $user['name'], $plot['Label'], (int) $row['PltID'], (int) $rejectedRequest['GardenerID'], $rejectedRequest['GardenerName'], (int) $user['id'], (int) $rejectedRequest['AppID']);
                            }
                        }
                        $autoRejected = count($rejectedRequests);
                    }
                }

                $eventType = $decision === 'approve' ? 'Request Accepted' : 'Request Rejected';
                recordPlotEvent($pdo, $eventType, 'staff', $user['name'], $plot['Label'], (int) $row['PltID'], (int) $row['GardenerID'], $row['GardenerName'], (int) $user['id'], (int) $appId);

                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            respond(['ok' => true, 'auto_rejected' => $autoRejected]);
        }

        case 'pending_resource_txns': {
            $user = requireJsonRole('staff');
            $rows = $pdo->query("
                SELECT T.TxnID, T.GardenerID, G.Name AS GardenerName, R.Name AS ResourceName, T.Qty,
                       T.RequestType, T.RequestNotes, T.RequestedAt
                FROM RESOURCE_TXN T
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID
                JOIN RESOURCE R ON R.ResourceID = T.ResourceID
                WHERE T.Status = 'Requested' ORDER BY T.RequestedAt ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond([
                'ok' => true,
                'transactions' => $rows,
                'current_gardener_id' => $user['ids']['customer'] ?? null,
            ]);
        }

        case 'add_resource': {
            $user = requireJsonRole('staff');
            $name = trim($_POST['name'] ?? '');
            $qty = $_POST['qty'] ?? '';
            if ($name === '' || mb_strlen($name) > 80 || !ctype_digit((string) $qty) || (int) $qty < 1 || (int) $qty > 100000) {
                respond(['ok' => false, 'error' => 'Enter an item name and a quantity from 1 to 100,000.'], 422);
            }

            $pdo->beginTransaction();
            // If a resource with the same name already exists, add to that row
            // instead of creating a duplicate entry.
            $existing = $pdo->prepare('SELECT ResourceID FROM RESOURCE WHERE LOWER(Name) = LOWER(?) FOR UPDATE');
            $existing->execute([$name]);
            $existingId = $existing->fetchColumn();

            if ($existingId) {
                $resourceId = (int) $existingId;
                $pdo->prepare('UPDATE RESOURCE SET TotalQty = TotalQty + ?, AvailableQty = AvailableQty + ? WHERE ResourceID = ?')
                    ->execute([(int) $qty, (int) $qty, $resourceId]);
            } else {
                $pdo->prepare('INSERT INTO RESOURCE (Name, TotalQty, AvailableQty) VALUES (?, ?, ?)')
                    ->execute([$name, (int) $qty, (int) $qty]);
                $resourceId = (int) $pdo->lastInsertId();
            }
            recordResourceEvent($pdo, $resourceId, 'Added', (int) $qty, 'staff', $user['name'], null, null, (int) $user['id']);
            $pdo->commit();
            respond(['ok' => true]);
        }

        case 'process_resource_txn': {
            $user = requireJsonRole('staff');
            $txnId = $_POST['txn_id'] ?? '';
            $decision = $_POST['decision'] ?? '';
            if (!ctype_digit((string) $txnId) || !in_array($decision, ['approve', 'reject'], true)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $pdo->beginTransaction();
            $txn = $pdo->prepare("
                SELECT T.GardenerID, G.Name AS GardenerName, T.ResourceID, T.Qty, T.Status,
                       T.RequestType, T.RequestNotes, T.SourcePersonalItemID
                FROM RESOURCE_TXN T
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID
                WHERE T.TxnID = ? FOR UPDATE
            ");
            $txn->execute([(int) $txnId]);
            $row = $txn->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['Status'] !== 'Requested') {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'Already processed.'], 409);
            }
            if (isset($user['ids']['customer']) && (int) $user['ids']['customer'] === (int) $row['GardenerID']) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'You cannot approve or reject your own resource or donation request.'], 403);
            }

            $requestedQty = (int) $row['Qty'];

            // A combined request can be processed partially: the coordinator picks
            // how many units this decision applies to. Whatever is left over stays
            // on the original row as a still-pending "Requested" entry, so it can
            // be decided on later instead of being auto-approved/auto-rejected.
            $chosenQtyRaw = $_POST['qty'] ?? $requestedQty;
            if (!ctype_digit((string) $chosenQtyRaw)) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'Invalid quantity.'], 422);
            }
            $chosenQty = (int) $chosenQtyRaw;
            if ($chosenQty < 1 || $chosenQty > $requestedQty) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => "Choose a quantity between 1 and {$requestedQty}."], 422);
            }
            $remainder = $requestedQty - $chosenQty;
            $rejectionReason = trim($_POST['reason'] ?? '');
            if ($decision === 'reject' && ($rejectionReason === '' || mb_strlen($rejectionReason) > 1000)) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'A rejection reason of no more than 1,000 characters is required.'], 422);
            }
            if ($decision === 'approve' && $remainder > 0 && ($rejectionReason === '' || mb_strlen($rejectionReason) > 1000)) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'A reason for partial approval of no more than 1,000 characters is required.'], 422);
            }

            if ($decision === 'approve') {
                $res = $pdo->prepare('SELECT TotalQty FROM RESOURCE WHERE ResourceID = ? FOR UPDATE');
                $res->execute([$row['ResourceID']]);
                $totalQty = $res->fetchColumn();
                if ($totalQty === false) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Resource not found.'], 404);
                }

                if ($row['RequestType'] === 'Donation') {
                    if ($row['SourcePersonalItemID'] !== null) {
                        $personal = $pdo->prepare('SELECT Qty FROM PERSONAL_INVENTORY WHERE ItemID = ? AND GardenerID = ? FOR UPDATE');
                        $personal->execute([(int) $row['SourcePersonalItemID'], (int) $row['GardenerID']]);
                        $personalQty = $personal->fetchColumn();
                        if ($personalQty === false || (int) $personalQty < $chosenQty) {
                            $pdo->rollBack();
                            respond(['ok' => false, 'error' => 'The gardener no longer has enough of this personal item to approve the donation.'], 409);
                        }
                    }

                    $pdo->prepare('UPDATE RESOURCE SET TotalQty = TotalQty + ?, AvailableQty = AvailableQty + ? WHERE ResourceID = ?')
                        ->execute([$chosenQty, $chosenQty, (int) $row['ResourceID']]);

                    if ($row['SourcePersonalItemID'] !== null) {
                        $remainingPersonalQty = (int) $personalQty - $chosenQty;
                        if ($remainingPersonalQty === 0) {
                            $pdo->prepare('DELETE FROM PERSONAL_INVENTORY WHERE ItemID = ? AND GardenerID = ?')
                                ->execute([(int) $row['SourcePersonalItemID'], (int) $row['GardenerID']]);
                        } else {
                            $pdo->prepare('UPDATE PERSONAL_INVENTORY SET Qty = ? WHERE ItemID = ? AND GardenerID = ?')
                                ->execute([$remainingPersonalQty, (int) $row['SourcePersonalItemID'], (int) $row['GardenerID']]);
                        }
                    }

                    recordResourceEvent($pdo, (int) $row['ResourceID'], 'Added', $chosenQty, 'staff', $user['name'], (int) $row['GardenerID'], $row['GardenerName'], (int) $user['id']);
                    if ($remainder > 0) {
                        $pdo->prepare("UPDATE RESOURCE_TXN SET Qty = ?, ProcessedQty = ?, Status = 'Rejected', CoordID = ?, RejectionReason = ? WHERE TxnID = ?")
                            ->execute([$remainder, $chosenQty, $user['id'], $rejectionReason, (int) $txnId]);
                    } else {
                        $pdo->prepare("UPDATE RESOURCE_TXN SET Status = 'Donated', ProcessedQty = ?, CoordID = ?, ApprovedAt = NOW() WHERE TxnID = ?")
                            ->execute([$chosenQty, $user['id'], (int) $txnId]);
                    }
                    $pdo->commit();
                    respond(['ok' => true]);
                }

                $active = $pdo->prepare("SELECT COALESCE(SUM(Qty), 0) FROM RESOURCE_TXN WHERE ResourceID = ? AND Status IN ('Approved', 'Return Requested')");
                $active->execute([(int) $row['ResourceID']]);
                $availableQty = max(0, (int) $totalQty - (int) $active->fetchColumn());
                if ($chosenQty > $availableQty) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Not enough stock left to approve that many.'], 409);
                }

                $plot = $pdo->prepare("SELECT PltID, Label FROM PLOT WHERE GardenerID = ? AND Status = 'Occupied' ORDER BY PltID LIMIT 1");
                $plot->execute([(int) $row['GardenerID']]);
                $plotRow = $plot->fetch(PDO::FETCH_ASSOC);
                $plotId = $plotRow ? (int) $plotRow['PltID'] : null;
                $plotLabel = $plotRow['Label'] ?? null;

                // Fold the approved amount into the gardener's existing borrower
                // assignment for this resource rather than adding a new row.
                mergeOrCreateApprovalRow($pdo, (int) $row['GardenerID'], (int) $row['ResourceID'], $chosenQty, (int) $user['id'], $plotId);
                recordResourceEvent($pdo, (int) $row['ResourceID'], 'Borrowed', $chosenQty, 'customer', $row['GardenerName'], (int) $row['GardenerID'], $row['GardenerName'], null, $plotId, $plotLabel);

                if ($remainder > 0) {
                    // The approved amount is recorded as borrowed; close the
                    // remainder as rejected so it does not remain in the queue.
                    $pdo->prepare("UPDATE RESOURCE_TXN SET Qty = ?, ProcessedQty = ?, Status = 'Rejected', CoordID = ?, RejectionReason = ? WHERE TxnID = ?")
                        ->execute([$remainder, $chosenQty, $user['id'], $rejectionReason, (int) $txnId]);
                } else {
                    // Nothing left over — the original "Requested" row has been
                    // fully folded into the approval above and is no longer needed.
                    $pdo->prepare('DELETE FROM RESOURCE_TXN WHERE TxnID = ?')->execute([(int) $txnId]);
                }

                $active->execute([(int) $row['ResourceID']]);
                $availableQty = max(0, (int) $totalQty - (int) $active->fetchColumn());
                $pdo->prepare('UPDATE RESOURCE SET AvailableQty = ? WHERE ResourceID = ?')
                    ->execute([$availableQty, (int) $row['ResourceID']]);
            } else {
                if ($remainder > 0) {
                    // Leave the rest of the request pending — don't approve it.
                    $pdo->prepare('UPDATE RESOURCE_TXN SET Qty = ? WHERE TxnID = ?')
                        ->execute([$remainder, (int) $txnId]);
                    $pdo->prepare("
                        INSERT INTO RESOURCE_TXN
                            (GardenerID, CoordID, ResourceID, Qty, RequestType, RequestNotes, SourcePersonalItemID, ProcessedQty, Status, RejectionReason)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'Rejected', ?)
                    ")->execute([
                        (int) $row['GardenerID'], (int) $user['id'], (int) $row['ResourceID'],
                        $chosenQty, $row['RequestType'], $row['RequestNotes'], $row['SourcePersonalItemID'], $rejectionReason,
                    ]);
                } else {
                    $pdo->prepare("UPDATE RESOURCE_TXN SET Status = 'Rejected', CoordID = ?, RejectionReason = ? WHERE TxnID = ?")
                        ->execute([$user['id'], $rejectionReason, (int) $txnId]);
                }
            }
            $pdo->commit();
            respond(['ok' => true]);
        }

        case 'create_plot': {
            $user = requireJsonRole('staff');
            $label = trim($_POST['label'] ?? '');
            if ($label === '' || strlen($label) > 80) {
                respond(['ok' => false, 'error' => 'Enter a plot name up to 80 characters.'], 422);
            }
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/', $label)) {
                respond(['ok' => false, 'error' => 'Plot names may use letters, numbers, spaces, dots, hyphens, and underscores.'], 422);
            }

            $pdo->beginTransaction();
            try {
                $duplicate = $pdo->prepare('SELECT 1 FROM PLOT WHERE LOWER(Label) = LOWER(?) LIMIT 1 FOR UPDATE');
                $duplicate->execute([$label]);
                if ($duplicate->fetchColumn()) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'A plot with that name already exists.'], 409);
                }

                $pdo->prepare("INSERT INTO PLOT (Label, GardenerID, Status) VALUES (?, NULL, 'Available')")
                    ->execute([$label]);
                recordPlotEvent($pdo, 'Plot Added', 'staff', $user['name'], $label, (int) $pdo->lastInsertId(), null, null, (int) $user['id']);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            respond(['ok' => true]);
        }

        case 'delete_plot': {
            requireJsonRole('staff');
            $plotId = $_POST['plot_id'] ?? '';
            if (!ctype_digit((string) $plotId)) {
                respond(['ok' => false, 'error' => 'Invalid plot.'], 422);
            }

            $plot = $pdo->prepare('SELECT GardenerID, Status FROM PLOT WHERE PltID = ?');
            $plot->execute([(int) $plotId]);
            $row = $plot->fetch(PDO::FETCH_ASSOC);
            if (!$row) respond(['ok' => false, 'error' => 'Plot not found.'], 404);
            if ($row['GardenerID'] !== null || $row['Status'] !== 'Available') {
                respond(['ok' => false, 'error' => 'Only an available, unassigned plot can be deleted.'], 409);
            }

            $references = $pdo->prepare('SELECT 1 FROM PLOT_APPLICATION WHERE PltID = ? LIMIT 1');
            $references->execute([(int) $plotId]);
            if ($references->fetchColumn()) {
                respond(['ok' => false, 'error' => 'This plot has application history and cannot be deleted.'], 409);
            }

            $pdo->prepare('DELETE FROM PLOT WHERE PltID = ?')->execute([(int) $plotId]);
            respond(['ok' => true]);
        }

        case 'all_plots': {
            requireJsonRole('staff');
            syncLegacyCommunityPlots($pdo);
            $rows = $pdo->query("
                SELECT P.PltID,
                       P.Label,
                       CASE
                         WHEN P.Status = 'Occupied' OR P.GardenerID IS NOT NULL THEN 'Occupied'
                         WHEN P.Status = 'Pending Approval' OR EXISTS (
                           SELECT 1 FROM PLOT_APPLICATION PA
                           WHERE PA.PltID = P.PltID AND PA.Status = 'Pending' AND PA.RequestType = 'Apply'
                         ) THEN 'Pending Approval'
                         ELSE 'Available'
                       END AS Status,
                       G.Name AS GardenerName
                FROM PLOT P
                LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = P.GardenerID
                ORDER BY P.Label
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'plots' => $rows]);
        }

        case 'all_resources': {
            requireInventoryManager();
            $rows = $pdo->query("
                SELECT R.ResourceID, R.Name, R.TotalQty, R.AvailableQty,
                       G.Name AS BorrowerName, T.TxnID, T.Qty AS BorrowedQty, T.Status AS BorrowerStatus,
                       COALESCE(AssignedPlot.Label, CurrentPlot.Label) AS PlotLabel
                FROM RESOURCE R
                LEFT JOIN RESOURCE_TXN T
                  ON T.ResourceID = R.ResourceID AND T.Status IN ('Approved', 'Return Requested')
                LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID
                LEFT JOIN PLOT AssignedPlot ON AssignedPlot.PltID = T.PltID
                LEFT JOIN PLOT CurrentPlot ON CurrentPlot.PltID = (
                    SELECT MIN(P2.PltID) FROM PLOT P2
                    WHERE P2.GardenerID = T.GardenerID AND P2.Status = 'Occupied'
                )
                ORDER BY R.Name
            ")->fetchAll(PDO::FETCH_ASSOC);
            $resources = [];
            foreach ($rows as $row) {
                $resourceId = (int) $row['ResourceID'];
                if (!isset($resources[$resourceId])) {
                    $resources[$resourceId] = [
                        'ResourceID' => $resourceId,
                        'Name' => $row['Name'],
                        'TotalQty' => (int) $row['TotalQty'],
                        'AvailableQty' => (int) $row['TotalQty'],
                        'Borrowers' => [],
                    ];
                }
                if ($row['TxnID'] !== null) {
                    $resources[$resourceId]['AvailableQty'] = max(0, $resources[$resourceId]['AvailableQty'] - (int) $row['BorrowedQty']);
                    $resources[$resourceId]['Borrowers'][] = [
                        'TxnID' => (int) $row['TxnID'],
                        'Name' => $row['BorrowerName'],
                        'Qty' => (int) $row['BorrowedQty'],
                        'Status' => $row['BorrowerStatus'],
                        'PlotLabel' => $row['PlotLabel'],
                    ];
                }
            }
            respond(['ok' => true, 'resources' => array_values($resources)]);
        }

        case 'update_resource_total': {
            $user = requireInventoryManager();
            $resourceId = $_POST['resource_id'] ?? '';
            $totalQtyRaw = $_POST['total_qty'] ?? '';
            if (!ctype_digit((string) $resourceId) || !ctype_digit((string) $totalQtyRaw) || (int) $totalQtyRaw > 100000) {
                respond(['ok' => false, 'error' => 'Choose a resource and enter a total quantity from 0 to 100,000.'], 422);
            }

            $resourceId = (int) $resourceId;
            $totalQty = (int) $totalQtyRaw;
            $pdo->beginTransaction();
            try {
                $resourceStmt = $pdo->prepare('SELECT ResourceID FROM RESOURCE WHERE ResourceID = ? FOR UPDATE');
                $resourceStmt->execute([$resourceId]);
                if ($resourceStmt->fetchColumn() === false) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Resource not found.'], 404);
                }

                $borrowedStmt = $pdo->prepare("SELECT COALESCE(SUM(Qty), 0) FROM RESOURCE_TXN WHERE ResourceID = ? AND Status IN ('Approved', 'Return Requested')");
                $borrowedStmt->execute([$resourceId]);
                $borrowedQty = (int) $borrowedStmt->fetchColumn();
                if ($totalQty < $borrowedQty) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => "Total quantity cannot be less than the {$borrowedQty} units currently assigned to gardeners."], 409);
                }

                $availableQty = $totalQty - $borrowedQty;
                $pdo->prepare('UPDATE RESOURCE SET TotalQty = ?, AvailableQty = ? WHERE ResourceID = ?')
                    ->execute([$totalQty, $availableQty, $resourceId]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            respond(['ok' => true]);
        }

        case 'resource_records': {
            requireJsonRole('staff');
            $selectedDate = $_GET['date'] ?? date('Y-m-d');
            if (!is_string($selectedDate)) {
                respond(['ok' => false, 'error' => 'Choose a valid date.'], 422);
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
            if (!$date || $date->format('Y-m-d') !== $selectedDate) {
                respond(['ok' => false, 'error' => 'Choose a valid date.'], 422);
            }
            $startAt = $date->format('Y-m-d') . ' 00:00:00';
            $endAt = $date->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
            $stmt = $pdo->prepare("
                SELECT E.EventID, R.Name AS ResourceName, E.EventType AS Action, E.Qty,
                       E.ActorType, E.ActorName, E.GardenerName, E.PlotLabel, E.OccurredAt
                FROM RESOURCE_EVENT E
                JOIN RESOURCE R ON R.ResourceID = E.ResourceID
                WHERE E.OccurredAt >= ? AND E.OccurredAt < ?
                ORDER BY E.OccurredAt DESC, E.EventID DESC
            ");
            $stmt->execute([$startAt, $endAt]);
            respond(['ok' => true, 'date' => $selectedDate, 'records' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'plot_records': {
            requireJsonRole('staff');
            $selectedDate = $_GET['date'] ?? date('Y-m-d');
            if (!is_string($selectedDate)) {
                respond(['ok' => false, 'error' => 'Choose a valid date.'], 422);
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
            if (!$date || $date->format('Y-m-d') !== $selectedDate) {
                respond(['ok' => false, 'error' => 'Choose a valid date.'], 422);
            }
            $startAt = $date->format('Y-m-d') . ' 00:00:00';
            $endAt = $date->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
            $stmt = $pdo->prepare("
                SELECT EventID, PlotLabel, EventType AS Action, ActorType, ActorName,
                       GardenerName, OccurredAt
                FROM PLOT_EVENT
                WHERE OccurredAt >= ? AND OccurredAt < ?
                ORDER BY OccurredAt DESC, EventID DESC
            ");
            $stmt->execute([$startAt, $endAt]);
            respond(['ok' => true, 'date' => $selectedDate, 'records' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'request_resource_return': {
            $user = requireJsonRole('staff');
            $txnId = $_POST['txn_id'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            if (!ctype_digit((string) $txnId)) {
                respond(['ok' => false, 'error' => 'Invalid transaction.'], 422);
            }
            if ($reason === '' || mb_strlen($reason) > 1000) {
                respond(['ok' => false, 'error' => 'A return-request reason of no more than 1,000 characters is required.'], 422);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT T.GardenerID, G.Name AS GardenerName, T.ResourceID, T.PltID, P.Label AS PlotLabel, T.Qty, T.Status FROM RESOURCE_TXN T JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID LEFT JOIN PLOT P ON P.PltID = T.PltID WHERE T.TxnID = ? FOR UPDATE");
            $stmt->execute([(int) $txnId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['Status'] !== 'Approved') {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'This item is no longer awaiting return.'], 409);
            }

            // The coordinator can choose to request back only part of a combined
            // borrower assignment; the rest stays with the gardener as approved.
            $borrowedQty = (int) $row['Qty'];
            $returnQtyRaw = $_POST['qty'] ?? $borrowedQty;
            if (!ctype_digit((string) $returnQtyRaw)) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'Invalid return quantity.'], 422);
            }
            $returnQty = (int) $returnQtyRaw;
            if ($returnQty < 1 || $returnQty > $borrowedQty) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => "Choose a quantity between 1 and {$borrowedQty}."], 422);
            }

            $pltId = $row['PltID'] === null ? null : (int) $row['PltID'];
            mergeOrCreateReturnRequestRow($pdo, (int) $row['GardenerID'], (int) $row['ResourceID'], $returnQty, (int) $user['id'], $pltId, $reason);
            recordResourceEvent($pdo, (int) $row['ResourceID'], 'Return Requested', $returnQty, 'staff', $user['name'], (int) $row['GardenerID'], $row['GardenerName'], (int) $user['id'], $pltId, $row['PlotLabel']);

            $remaining = $borrowedQty - $returnQty;
            if ($remaining > 0) {
                $pdo->prepare('UPDATE RESOURCE_TXN SET Qty = ? WHERE TxnID = ?')->execute([$remaining, (int) $txnId]);
            } else {
                $pdo->prepare('DELETE FROM RESOURCE_TXN WHERE TxnID = ?')->execute([(int) $txnId]);
            }

            $pdo->commit();
            respond(['ok' => true]);
        }

            // ---------------------------------------------------------
        // Plots & Crops Endpoints
        // ---------------------------------------------------------

        case 'get_my_plots': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("SELECT * FROM GARDEN_PLOTS WHERE GardenerID = ? ORDER BY PlantedDate DESC");
            $stmt->execute([$user['id']]);
            respond(['ok' => true, 'plots' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'add_crop_log': {
            $user = requireJsonRole('customer');
            foreach (['crop_name', 'planted_date', 'notes', 'est_harvest_date'] as $field) {
                if (isset($_POST[$field]) && !is_string($_POST[$field])) {
                    respond(['ok' => false, 'error' => 'Crop details must be submitted as text values.'], 422);
                }
            }
            $crop = trim($_POST['crop_name'] ?? '');
            $planted = trim($_POST['planted_date'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $harvest = $_POST['est_harvest_date'] ?? null;
            $harvest = $harvest === null || trim($harvest) === '' ? null : trim($harvest);
            $parsedPlantedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $planted);
            $parsedHarvestDate = $harvest === null ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $harvest);

            if ($crop === '' || mb_strlen($crop) > 60) {
                respond(['ok' => false, 'error' => 'Crop name is required and must be 60 characters or fewer.'], 422);
            }
            if (!$parsedPlantedDate || $parsedPlantedDate->format('Y-m-d') !== $planted) {
                respond(['ok' => false, 'error' => 'Choose a valid planted date.'], 422);
            }
            if ($harvest !== null && (!$parsedHarvestDate || $parsedHarvestDate->format('Y-m-d') !== $harvest)) {
                respond(['ok' => false, 'error' => 'Choose a valid estimated harvest date.'], 422);
            }
            if (mb_strlen($notes) > 1000) {
                respond(['ok' => false, 'error' => 'Initial notes must be 1,000 characters or fewer.'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO GARDEN_PLOTS (GardenerID, CropName, PlantedDate, EstHarvestDate, Notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $crop, $planted, $harvest, $notes]);
            respond(['ok' => true]);
        }

        case 'update_crop_status': {
            $user = requireJsonRole('customer');
            $plotId = (int)($_POST['plot_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if (!in_array($status, ['Planted', 'Growing', 'Harvested', 'Failed'])) {
                respond(['ok' => false, 'error' => 'Invalid status.'], 422);
            }

            $pdo->beginTransaction();
            try {
                $cropStmt = $pdo->prepare("SELECT CropName, Status FROM GARDEN_PLOTS WHERE PlotID = ? AND GardenerID = ? FOR UPDATE");
                $cropStmt->execute([$plotId, $user['id']]);
                $crop = $cropStmt->fetch(PDO::FETCH_ASSOC);
                if (!$crop) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Crop not found in your garden log.'], 404);
                }

                if ($crop['Status'] === $status) {
                    $pdo->commit();
                    respond(['ok' => true, 'history_added' => false]);
                }

                $pdo->prepare("UPDATE GARDEN_PLOTS SET Status = ? WHERE PlotID = ? AND GardenerID = ?")
                    ->execute([$status, $plotId, $user['id']]);

                $plotStmt = $pdo->prepare("SELECT PltID FROM PLOT WHERE GardenerID = ? AND Status = 'Occupied' ORDER BY PltID LIMIT 1");
                $plotStmt->execute([$user['id']]);
                $pltId = $plotStmt->fetchColumn();
                $pltId = $pltId === false ? null : (int) $pltId;
                $statusNote = "Crop status changed from {$crop['Status']} to {$status}.";

                $pdo->prepare("INSERT INTO CROP_LOG (GardenerID, GardenPlotID, PltID, CropName, MaintenanceNotes) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$user['id'], $plotId, $pltId, htmlspecialchars($crop['CropName'], ENT_QUOTES, 'UTF-8'), htmlspecialchars($statusNote, ENT_QUOTES, 'UTF-8')]);

                $pdo->commit();
                respond(['ok' => true, 'history_added' => true]);
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        }

        // ---------------------------------------------------------
        // Community Map Endpoints
        // ---------------------------------------------------------

                case 'get_community_map': {
                        $user = requireJsonRole('customer');
                        syncLegacyCommunityPlots($pdo);
                        $stmt = $pdo->prepare("
                                SELECT P.PltID AS PlotID,
                                             P.Label AS PlotName,
                                             CASE
                                                 WHEN P.Status = 'Occupied' OR P.GardenerID IS NOT NULL THEN 'Occupied'
                                                 WHEN P.Status = 'Pending Approval' OR EXISTS (
                                                     SELECT 1 FROM PLOT_APPLICATION PA
                                                     WHERE PA.PltID = P.PltID AND PA.Status = 'Pending' AND PA.RequestType = 'Apply'
                                                 ) THEN 'Pending Approval'
                                                 ELSE 'Available'
                                             END AS Status,
                                             P.GardenerID AS OccupantID,
                                             G.Name AS OccupantName,
                                             CASE WHEN P.GardenerID = ? THEN 1 ELSE 0 END AS IsMine,
                                             EXISTS (
                                                 SELECT 1 FROM PLOT_APPLICATION PA
                                                 WHERE PA.PltID = P.PltID AND PA.GardenerID = ?
                                                     AND PA.Status = 'Pending' AND PA.RequestType = 'Unassign'
                                             ) AS UnassignmentPending,
                                             EXISTS (
                                                 SELECT 1 FROM PLOT_APPLICATION PA
                                                 WHERE PA.PltID = P.PltID AND PA.GardenerID = ?
                                                     AND PA.Status = 'Pending' AND PA.RequestType = 'Apply'
                                             ) AS ApplicationPending
                                FROM PLOT P
                                LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = P.GardenerID
                                ORDER BY P.Label ASC
                        ");
                        $stmt->execute([$user['id'], $user['id'], $user['id']]);
                        $plots = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $rejectedStmt = $pdo->prepare("
                            SELECT PA.AppID, P.Label AS PlotName, PA.RequestType, PA.RejectionReason
                            FROM PLOT_APPLICATION PA
                            JOIN PLOT P ON P.PltID = PA.PltID
                            WHERE PA.GardenerID = ? AND PA.Status = 'Rejected' AND PA.RequestType IN ('Apply', 'Unassign')
                            ORDER BY PA.AppID DESC
                        ");
                        $rejectedStmt->execute([$user['id']]);
                        $rejectedApplications = $rejectedStmt->fetchAll(PDO::FETCH_ASSOC);
            
                    respond(['ok' => true, 'plots' => $plots, 'rejected_applications' => $rejectedApplications]);
        }

        case 'request_garden_plot': {
            $user = requireJsonRole('customer');
            $plotId = $_POST['plot_id'] ?? '';
            if (!ctype_digit((string) $plotId)) respond(['ok' => false, 'error' => 'Invalid plot.'], 422);

            $pdo->beginTransaction();
            try {
                $check = $pdo->prepare('SELECT PltID, Label, Status, GardenerID FROM PLOT WHERE PltID = ? FOR UPDATE');
                $check->execute([(int) $plotId]);
                $plot = $check->fetch(PDO::FETCH_ASSOC);
                if (!$plot || $plot['Status'] !== 'Available' || $plot['GardenerID'] !== null) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'This plot is no longer available.']);
                }

                $existing = $pdo->prepare("SELECT 1 FROM PLOT_APPLICATION WHERE GardenerID = ? AND PltID = ? AND Status = 'Pending' AND RequestType = 'Apply' LIMIT 1");
                $existing->execute([$user['id'], (int) $plotId]);
                if ($existing->fetchColumn()) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'You already requested this plot.'], 409);
                }

                $pdo->prepare("INSERT INTO PLOT_APPLICATION (GardenerID, PltID, Status, RequestType) VALUES (?, ?, 'Pending', 'Apply')")
                    ->execute([$user['id'], (int) $plotId]);
                recordPlotEvent($pdo, 'Request Assignment', 'customer', $user['name'], $plot['Label'], (int) $plotId, (int) $user['id'], $user['name'], null, (int) $pdo->lastInsertId());
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            respond(['ok' => true]);
        }

        // ---------------- ADMIN ----------------

        case 'stats': {
            requireJsonRole('admin');
            $count = fn($sql) => (int) $pdo->query($sql)->fetchColumn();
            respond(['ok' => true, 'stats' => [
                'admins' => $count("SELECT COUNT(*) FROM SYSTEM_ADMINISTRATOR WHERE Status = 'Active'"),
                'gardeners' => $count("SELECT COUNT(*) FROM COMMUNITY_GARDENER WHERE Status = 'Active'"),
                'coordinators' => $count("SELECT COUNT(*) FROM GARDEN_COORDINATOR WHERE Status = 'Active'"),
                'plots_occupied' => $count("SELECT COUNT(*) FROM PLOT WHERE Status = 'Occupied'"),
                'plots_available' => $count("SELECT COUNT(*) FROM PLOT WHERE Status = 'Available'"),
                'pending_applications' => $count("SELECT COUNT(*) FROM PLOT_APPLICATION WHERE Status = 'Pending'"),
                'pending_resource_txns' => $count("SELECT COUNT(*) FROM RESOURCE_TXN WHERE Status = 'Requested'"),
                'pending_signups' => $count("SELECT COUNT(*) FROM SIGNUP_REQUEST WHERE Status = 'Pending'"),
                'pending_coordinator_applications' => $count("SELECT COUNT(*) FROM COORDINATOR_APPLICATION WHERE Status = 'Pending'"),
                'active_listings' => $count("SELECT COUNT(*) FROM EXCHANGE_LISTING WHERE ListingID NOT IN (SELECT ListingID FROM EXCHANGE_ORDER)"),
                'completed_trades' => $count("SELECT COUNT(*) FROM EXCHANGE_ORDER"),
            ]]);
        }

        case 'accounts': {
            $user = requireJsonRole('admin');
            $gardeners = $pdo->query("SELECT GardenerID AS id, Name, Email, COALESCE(NULLIF(Status, ''), 'Active') AS Status, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location FROM COMMUNITY_GARDENER WHERE COALESCE(NULLIF(Status, ''), 'Active') IN ('Active', 'Disabled') ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
            $coordinators = $pdo->query("SELECT CoordID AS id, Name, Email, COALESCE(NULLIF(Status, ''), 'Active') AS Status, Shift, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location FROM GARDEN_COORDINATOR WHERE COALESCE(NULLIF(Status, ''), 'Active') IN ('Active', 'Disabled') ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
            $admins = $pdo->query("SELECT AdminID AS id, Name, Email, COALESCE(NULLIF(Status, ''), 'Active') AS Status, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location FROM SYSTEM_ADMINISTRATOR WHERE COALESCE(NULLIF(Status, ''), 'Active') IN ('Active', 'Disabled') ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
            
            respond(['ok' => true, 'current_user_id' => $user['id'], 'gardeners' => $gardeners, 'coordinators' => $coordinators, 'admins' => $admins]);
        }

        case 'pending_signups': {
            requireJsonRole('admin');
            $rows = $pdo->query("
                SELECT RequestID, FirstName, LastName, Age, Location, Email, Role, Shift, RequestedAt
                FROM SIGNUP_REQUEST WHERE Status = 'Pending' ORDER BY RequestedAt ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'requests' => $rows]);
        }

        // Everything the admin needs to decide on one registration request.
        case 'signup_request_details': {
            requireJsonRole('admin');
            $requestId = $_GET['request_id'] ?? '';
            if (!ctype_digit((string) $requestId)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $stmt = $pdo->prepare("
                SELECT RequestID, FirstName, LastName, Age, Location, Email, Status, RequestedAt
                FROM SIGNUP_REQUEST WHERE RequestID = ?
            ");
            $stmt->execute([(int) $requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$request) respond(['ok' => false, 'error' => 'Registration request not found.'], 404);

            // Approving would fail if the email already belongs to an account.
            $emailInUse = null;
            foreach (['COMMUNITY_GARDENER' => 'gardener', 'GARDEN_COORDINATOR' => 'coordinator', 'SYSTEM_ADMINISTRATOR' => 'administrator'] as $table => $label) {
                $check = $pdo->prepare("SELECT COALESCE(NULLIF(Status, ''), 'Active') FROM $table WHERE Email = ?");
                $check->execute([$request['Email']]);
                $status = $check->fetchColumn();
                if ($status !== false) {
                    $emailInUse = ['role' => $label, 'status' => $status];
                    break;
                }
            }

            $previous = $pdo->prepare("
                SELECT Status, RejectionReason, RequestedAt, ReviewedAt
                FROM SIGNUP_REQUEST WHERE Email = ? AND RequestID <> ?
                ORDER BY RequestedAt DESC LIMIT 5
            ");
            $previous->execute([$request['Email'], (int) $requestId]);

            respond([
                'ok' => true,
                'request' => $request,
                'email_in_use' => $emailInUse,
                'previous_requests' => $previous->fetchAll(PDO::FETCH_ASSOC),
            ]);
        }

        case 'my_coordinator_application': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("
                SELECT ApplicationID, Shift, Motivation, Status, RejectionReason, RequestedAt, ReviewedAt
                FROM COORDINATOR_APPLICATION
                WHERE GardenerID = ?
                ORDER BY ApplicationID DESC LIMIT 1
            ");
            $stmt->execute([$user['id']]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $coordinator = $pdo->prepare("SELECT CoordID, COALESCE(NULLIF(Status, ''), 'Active') AS Status FROM GARDEN_COORDINATOR WHERE GardenerID = ?");
            $coordinator->execute([$user['id']]);
            $coordinatorRecord = $coordinator->fetch(PDO::FETCH_ASSOC);
            if ($coordinatorRecord && $coordinatorRecord['Status'] === 'Active') {
                $user['roles'] = array_values(array_unique(array_merge($user['roles'], ['staff'])));
                $user['ids']['staff'] = (int) $coordinatorRecord['CoordID'];
            } else {
                $user['roles'] = array_values(array_diff($user['roles'], ['staff']));
                unset($user['ids']['staff']);
            }
            $_SESSION['user']['roles'] = $user['roles'];
            $_SESSION['user']['ids'] = $user['ids'];
            respond([
                'ok' => true,
                'application' => $application,
                'approved' => $coordinatorRecord && $coordinatorRecord['Status'] === 'Active',
                'has_coordinator' => $coordinatorRecord !== false,
            ]);
        }

        case 'apply_coordinator': {
            $user = requireJsonRole('customer');
            foreach (['shift', 'availability_days', 'motivation', 'gardening_experience', 'leadership_experience', 'agree_duties', 'agree_rules'] as $field) {
                if (isset($_POST[$field]) && !is_string($_POST[$field])) {
                    respond(['ok' => false, 'error' => 'Application fields must be submitted as text values.'], 422);
                }
            }
            $shift = trim($_POST['shift'] ?? '');
            $availabilityRaw = trim($_POST['availability_days'] ?? '');
            $availabilityDays = $availabilityRaw === '' ? [] : explode(',', $availabilityRaw);
            $motivation = trim($_POST['motivation'] ?? '');
            $gardeningExperience = trim($_POST['gardening_experience'] ?? '');
            $leadershipExperience = trim($_POST['leadership_experience'] ?? '');
            $agreeDuties = ($_POST['agree_duties'] ?? '') === '1';
            $agreeRules = ($_POST['agree_rules'] ?? '') === '1';
            $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $validExperiences = ['Beginner', '1-2 years', '3+ years'];

            if (!in_array($shift, ['Morning', 'Afternoon'], true)) {
                respond(['ok' => false, 'error' => 'Choose a valid preferred shift.'], 422);
            }
            if (!$availabilityDays || count(array_unique($availabilityDays)) !== count($availabilityDays)
                || array_diff($availabilityDays, $validDays)) {
                respond(['ok' => false, 'error' => 'Select one or more valid days of availability.'], 422);
            }
            if (!in_array($gardeningExperience, $validExperiences, true)) {
                respond(['ok' => false, 'error' => 'Choose a gardening experience level.'], 422);
            }
            if (mb_strlen($motivation) < 50 || mb_strlen($motivation) > 1000) {
                respond(['ok' => false, 'error' => 'Explain why you want to coordinate in 50–1,000 characters.'], 422);
            }
            if (mb_strlen($leadershipExperience) > 1000) {
                respond(['ok' => false, 'error' => 'Leadership or volunteer experience cannot exceed 1,000 characters.'], 422);
            }
            if (!$agreeDuties || !$agreeRules) {
                respond(['ok' => false, 'error' => 'Agree to both coordinator duties and garden rules before submitting.'], 422);
            }

            $pdo->beginTransaction();
            try {
                $coordinator = $pdo->prepare('SELECT CoordID FROM GARDEN_COORDINATOR WHERE GardenerID = ? FOR UPDATE');
                $coordinator->execute([$user['id']]);
                if ($coordinator->fetchColumn() !== false) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'You already have coordinator access.'], 409);
                }
                $pending = $pdo->prepare("SELECT ApplicationID FROM COORDINATOR_APPLICATION WHERE GardenerID = ? AND Status = 'Pending' FOR UPDATE");
                $pending->execute([$user['id']]);
                if ($pending->fetchColumn() !== false) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Your coordinator application is already under review.'], 409);
                }
                $pdo->prepare("
                    INSERT INTO COORDINATOR_APPLICATION
                        (GardenerID, Shift, AvailabilityDays, Motivation, GardeningExperience, LeadershipExperience, AgreedToDuties, AgreedToRules)
                    VALUES (?, ?, ?, ?, ?, ?, 1, 1)
                ")->execute([
                    $user['id'],
                    $shift,
                    implode(',', $availabilityDays),
                    $motivation,
                    $gardeningExperience,
                    $leadershipExperience === '' ? null : $leadershipExperience,
                ]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            respond(['ok' => true]);
        }

        case 'pending_coordinator_applications': {
            requireJsonRole('admin');
            $rows = $pdo->query("
                SELECT A.ApplicationID, A.GardenerID, A.Shift, A.Motivation, A.RequestedAt,
                       G.Name, G.Email, G.Location
                FROM COORDINATOR_APPLICATION A
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = A.GardenerID
                WHERE A.Status = 'Pending'
                ORDER BY A.RequestedAt ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'applications' => $rows]);
        }

        // The applicant's profile and garden activity, for reviewing one
        // coordinator application before deciding on it.
        case 'coordinator_application_details': {
            requireJsonRole('admin');
            $applicationId = $_GET['application_id'] ?? '';
            if (!ctype_digit((string) $applicationId)) {
                respond(['ok' => false, 'error' => 'Invalid application.'], 422);
            }

            $stmt = $pdo->prepare("
                SELECT A.ApplicationID, A.GardenerID, A.Shift, A.AvailabilityDays, A.Motivation,
                       A.GardeningExperience, A.LeadershipExperience, A.AgreedToDuties, A.AgreedToRules,
                       A.Status, A.RequestedAt,
                       G.Name, G.Email, G.Age, COALESCE(NULLIF(G.Location, ''), 'Not provided') AS Location,
                       COALESCE(NULLIF(G.Status, ''), 'Active') AS AccountStatus
                FROM COORDINATOR_APPLICATION A
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = A.GardenerID
                WHERE A.ApplicationID = ?
            ");
            $stmt->execute([(int) $applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$application) respond(['ok' => false, 'error' => 'Application not found.'], 404);

            $gardenerId = (int) $application['GardenerID'];
            $count = function (string $sql) use ($pdo, $gardenerId): int {
                $q = $pdo->prepare($sql);
                $q->execute([$gardenerId]);
                return (int) $q->fetchColumn();
            };

            $plots = $pdo->prepare("SELECT Label FROM PLOT WHERE GardenerID = ? AND Status = 'Occupied' ORDER BY Label");
            $plots->execute([$gardenerId]);

            // When the gardener's own registration was approved, if it went through a request.
            $memberSince = $pdo->prepare("SELECT ReviewedAt FROM SIGNUP_REQUEST WHERE Email = ? AND Status = 'Approved' ORDER BY ReviewedAt DESC LIMIT 1");
            $memberSince->execute([$application['Email']]);

            $previous = $pdo->prepare("
                SELECT Shift, Status, RejectionReason, RequestedAt, ReviewedAt
                FROM COORDINATOR_APPLICATION WHERE GardenerID = ? AND ApplicationID <> ?
                ORDER BY RequestedAt DESC LIMIT 5
            ");
            $previous->execute([$gardenerId, (int) $applicationId]);

            $activityStmt = $pdo->prepare("
                SELECT ActivityType, OccurredAt, Action, Subject, Details, PlotLabel
                FROM (
                    SELECT
                        'Crop' AS ActivityType,
                        L.LogID AS ActivityID,
                        L.LoggedAt AS OccurredAt,
                        'Maintenance logged' AS Action,
                        L.CropName AS Subject,
                        CONCAT_WS(' · ',
                            NULLIF(L.MaintenanceNotes, ''),
                            CASE WHEN L.HarvestYield IS NOT NULL AND L.HarvestYield <> '' THEN CONCAT('Yield: ', L.HarvestYield) END
                        ) AS Details,
                        P.Label AS PlotLabel
                    FROM CROP_LOG L
                    LEFT JOIN PLOT P ON P.PltID = L.PltID
                    WHERE L.GardenerID = ?

                    UNION ALL

                    SELECT
                        'Resource' AS ActivityType,
                        E.EventID AS ActivityID,
                        E.OccurredAt AS OccurredAt,
                        E.EventType AS Action,
                        R.Name AS Subject,
                        CONCAT(E.Qty, ' unit(s) · ', E.ActorName) AS Details,
                        E.PlotLabel AS PlotLabel
                    FROM RESOURCE_EVENT E
                    JOIN RESOURCE R ON R.ResourceID = E.ResourceID
                    WHERE E.GardenerID = ?
                ) AS GardenerActivity
                ORDER BY OccurredAt DESC, ActivityType, ActivityID DESC
            ");
            $activityStmt->execute([$gardenerId, $gardenerId]);

            respond([
                'ok' => true,
                'application' => $application,
                'member_since' => $memberSince->fetchColumn() ?: null,
                'activity' => [
                    'plots' => $plots->fetchAll(PDO::FETCH_COLUMN),
                    'crops_logged' => $count('SELECT COUNT(DISTINCT CropName) FROM CROP_LOG WHERE GardenerID = ?'),
                    'maintenance_entries' => $count('SELECT COUNT(*) FROM CROP_LOG WHERE GardenerID = ?'),
                    'items_borrowed' => $count("SELECT COALESCE(SUM(Qty), 0) FROM RESOURCE_TXN WHERE GardenerID = ? AND Status IN ('Approved', 'Return Requested')"),
                    'active_listings' => $count("SELECT COUNT(*) FROM EXCHANGE_BOARD WHERE GardenerID = ? AND Status = 'Active'"),
                    'history' => $activityStmt->fetchAll(PDO::FETCH_ASSOC),
                ],
                'previous_applications' => $previous->fetchAll(PDO::FETCH_ASSOC),
            ]);
        }

        case 'process_coordinator_application': {
            $admin = requireJsonRole('admin');
            $applicationId = $_POST['application_id'] ?? '';
            $decision = $_POST['decision'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            if (!ctype_digit((string) $applicationId) || !in_array($decision, ['approve', 'reject'], true)
                || ($decision === 'reject' && ($reason === '' || mb_strlen($reason) > 1000))) {
                respond(['ok' => false, 'error' => 'A rejection reason is required and must not exceed 1,000 characters.'], 422);
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    SELECT A.GardenerID, A.Shift, A.Status, G.Name, G.Email, G.PasswordHash, G.Location,
                           COALESCE(NULLIF(G.Status, ''), 'Active') AS GardenerStatus
                    FROM COORDINATOR_APPLICATION A
                    JOIN COMMUNITY_GARDENER G ON G.GardenerID = A.GardenerID
                    WHERE A.ApplicationID = ? FOR UPDATE
                ");
                $stmt->execute([(int) $applicationId]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$application || $application['Status'] !== 'Pending') {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'This coordinator application has already been reviewed.'], 409);
                }
                if ($decision === 'approve') {
                    if ($application['GardenerStatus'] !== 'Active') {
                        $pdo->rollBack();
                        respond(['ok' => false, 'error' => 'Coordinator access cannot be granted to an inactive gardener account.'], 409);
                    }
                    $pdo->prepare("
                        INSERT INTO GARDEN_COORDINATOR (GardenerID, Name, Email, PasswordHash, Shift, Location)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([
                        (int) $application['GardenerID'],
                        $application['Name'],
                        $application['Email'],
                        $application['PasswordHash'],
                        $application['Shift'],
                        $application['Location'],
                    ]);
                }
                $pdo->prepare("
                    UPDATE COORDINATOR_APPLICATION
                    SET Status = ?, RejectionReason = ?, ReviewedAt = NOW(), ReviewedBy = ?
                    WHERE ApplicationID = ?
                ")->execute([
                    $decision === 'approve' ? 'Approved' : 'Rejected',
                    $decision === 'reject' ? $reason : null,
                    $admin['id'],
                    (int) $applicationId,
                ]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
                    respond(['ok' => false, 'error' => 'Coordinator access could not be granted because this email is already assigned to another coordinator.'], 409);
                }
                throw $error;
            }
            respond(['ok' => true]);
        }

        case 'process_signup': {
            $user = requireJsonRole('admin');
            $requestId = $_POST['request_id'] ?? '';
            $decision = $_POST['decision'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            if (!ctype_digit((string) $requestId) || !in_array($decision, ['approve', 'reject'], true)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }
            if ($decision === 'reject' && ($reason === '' || mb_strlen($reason) > 1000)) {
                respond(['ok' => false, 'error' => 'Please provide a rejection reason of no more than 1,000 characters.'], 422);
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT * FROM SIGNUP_REQUEST WHERE RequestID = ? FOR UPDATE");
                $stmt->execute([(int) $requestId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['Status'] !== 'Pending') {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'This registration request has already been reviewed.'], 409);
                }
                if ($decision === 'approve') {
                    $dupe = $pdo->prepare("
                        SELECT 1 FROM COMMUNITY_GARDENER WHERE Email = ?
                        UNION SELECT 1 FROM GARDEN_COORDINATOR WHERE Email = ?
                        UNION SELECT 1 FROM SYSTEM_ADMINISTRATOR WHERE Email = ?
                    ");
                    $dupe->execute([$row['Email'], $row['Email'], $row['Email']]);
                    if ($dupe->fetchColumn()) {
                        $pdo->rollBack();
                        respond(['ok' => false, 'error' => 'That email is already in use.'], 409);
                    }
                    $name = trim($row['FirstName'] . ' ' . $row['LastName']);
                    $pdo->prepare("INSERT INTO COMMUNITY_GARDENER (Name, Email, PasswordHash, Age, Location) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$name, $row['Email'], $row['PasswordHash'], $row['Age'], $row['Location']]);
                }
                $pdo->prepare("UPDATE SIGNUP_REQUEST SET Status = ?, RejectionReason = ?, ReviewedAt = NOW(), ReviewedBy = ? WHERE RequestID = ?")
                    ->execute([
                        $decision === 'approve' ? 'Approved' : 'Rejected',
                        $decision === 'reject' ? $reason : null,
                        $user['id'],
                        (int) $requestId,
                    ]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
                    respond(['ok' => false, 'error' => 'That email is already in use.'], 409);
                }
                throw $error;
            }

            respond(['ok' => true]);
        }

        case 'create_admin': {
            requireJsonRole('admin');
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $name = trim($firstName . ' ' . $lastName);
            $email = trim($_POST['email'] ?? '');
            $age = $_POST['age'] ?? '';
            $location = trim($_POST['location'] ?? '');
            $password = $_POST['password'] ?? '';

            if (trim($name) === '' || $email === '' || trim((string)$age) === '' || $location === '' || $password === '') {
                respond(['ok' => false, 'error' => 'Please fill in all required fields.'], 422);
            }

            $errors = [];
            if (empty($name)) $errors[] = 'Name is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
            if (!ctype_digit((string)$age) || (int)$age < 18 || (int)$age > 120) $errors[] = 'Age must be between 18 and 120.';
            if (!in_array($location, NCR_CITIES, true)) $errors[] = 'Please select a valid NCR city.';
            if (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}$/', $password)) {
                $errors[] = 'Password must be 8+ chars with an uppercase, lowercase, number, and special character.';
            }

            if ($errors) respond(['ok' => false, 'error' => implode(' ', $errors)], 422);

            try {
                $pdo->prepare("INSERT INTO SYSTEM_ADMINISTRATOR (Name, Email, PasswordHash, Age, Location, Status) VALUES (?, ?, ?, ?, ?, 'Active')")
                    ->execute([
                        htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), 
                        $email, 
                        password_hash($password, PASSWORD_BCRYPT),
                        (int)$age,
                        htmlspecialchars($location, ENT_QUOTES, 'UTF-8')
                    ]);
                respond(['ok' => true]);
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                    respond(['ok' => false, 'error' => 'That email is already in use.'], 409);
                }
                error_log($e->getMessage());
                respond(['ok' => false, 'error' => 'Could not create administrator. Please try again.'], 500);
            }
        }
        
        case 'archive_account': {
            $user = requireJsonRole('admin');
            $table = $_POST['table'] ?? '';
            $id = $_POST['id'] ?? '';
            $map = [
                'gardener' => ['COMMUNITY_GARDENER', 'GardenerID'], 
                'coordinator' => ['GARDEN_COORDINATOR', 'CoordID'],
                'admin' => ['SYSTEM_ADMINISTRATOR', 'AdminID']
            ];
            if (!isset($map[$table]) || !ctype_digit((string) $id)) respond(['ok' => false, 'error' => 'Invalid request.'], 422);

            $idNum = (int) $id;
            
            // Prevent an admin from archiving themselves
            if ($table === 'admin' && $idNum === $user['id']) {
                respond(['ok' => false, 'error' => 'You cannot archive your own account.'], 403);
            }

            [$tbl, $col] = $map[$table];
            $pdo->beginTransaction();
            try {
                $gardenerName = null;
                $assignedPlots = [];
                if ($table === 'gardener') {
                    $reason = trim($_POST['reason'] ?? '');
                    $details = trim($_POST['details'] ?? '');
                    $allowedReasons = ['Inactive account', 'Spam or abuse', 'Policy violation', 'Other'];
                    if (!in_array($reason, $allowedReasons, true) || $details === '' || mb_strlen($details) > 1000) {
                        $pdo->rollBack();
                        respond(['ok' => false, 'error' => 'Choose a valid reason and explain it in 1,000 characters or fewer.'], 422);
                    }

                    $gardener = $pdo->prepare("SELECT Name FROM COMMUNITY_GARDENER WHERE GardenerID = ? AND Status <> 'Archived' FOR UPDATE");
                    $gardener->execute([$idNum]);
                    $gardenerName = $gardener->fetchColumn();
                    if ($gardenerName === false) {
                        $pdo->rollBack();
                        respond(['ok' => false, 'error' => 'Gardener not found or already archived.'], 404);
                    }

                    $activePlots = $pdo->prepare("SELECT PltID FROM PLOT WHERE GardenerID = ? AND Status = 'Occupied' FOR UPDATE");
                    $activePlots->execute([$idNum]);
                    $hasActivePlots = $activePlots->fetchColumn() !== false;
                    $borrowedResources = $pdo->prepare("
                        SELECT TxnID
                        FROM RESOURCE_TXN
                        WHERE GardenerID = ? AND Status IN ('Approved', 'Return Requested')
                        FOR UPDATE
                    ");
                    $borrowedResources->execute([$idNum]);
                    $hasBorrowedResources = $borrowedResources->fetchColumn() !== false;
                    if ($hasActivePlots || $hasBorrowedResources) {
                        $pdo->rollBack();
                        respond(['ok' => false, 'error' => 'Resolve active plots and borrowed resources before archiving.'], 409);
                    }

                    $pdo->prepare("
                        INSERT INTO ACCOUNT_ARCHIVE_NOTICE (GardenerID, AdminID, Reason, Details)
                        VALUES (?, ?, ?, ?)
                    ")->execute([$idNum, (int) $user['id'], $reason, $details]);
                    $plotsStmt = $pdo->prepare('SELECT PltID, Label FROM PLOT WHERE GardenerID = ? FOR UPDATE');
                    $plotsStmt->execute([$idNum]);
                    $assignedPlots = $plotsStmt->fetchAll(PDO::FETCH_ASSOC);
                }

                $pdo->prepare("UPDATE $tbl SET Status = 'Archived' WHERE $col = ?")->execute([$idNum]);
                if ($table === 'gardener') {
                    $pdo->prepare("UPDATE PLOT SET GardenerID = NULL, Status = 'Available' WHERE GardenerID = ?")->execute([$idNum]);
                    foreach ($assignedPlots as $plot) {
                        recordPlotEvent($pdo, 'Plot Unassigned', 'admin', $user['name'], $plot['Label'], (int) $plot['PltID'], $idNum, $gardenerName, (int) $user['id']);
                    }
                }
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }

            respond(['ok' => true]);
        }

        case 'send_archive_notice': {
            $user = requireJsonRole('admin');
            $id = $_POST['id'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            $details = trim($_POST['details'] ?? '');
            $allowedReasons = ['Inactive account', 'Spam or abuse', 'Policy violation', 'Other'];
            if (!ctype_digit((string) $id) || (int) $id < 1 || !in_array($reason, $allowedReasons, true)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }
            if ($details === '' || mb_strlen($details) > 1000) {
                respond(['ok' => false, 'error' => 'Explain the reason in 1,000 characters or fewer.'], 422);
            }

            $gardenerId = (int) $id;
            $pdo->beginTransaction();
            try {
                $gardenerStmt = $pdo->prepare("SELECT 1 FROM COMMUNITY_GARDENER WHERE GardenerID = ? AND Status <> 'Archived' FOR UPDATE");
                $gardenerStmt->execute([$gardenerId]);
                if (!$gardenerStmt->fetchColumn()) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Gardener not found or already archived.'], 404);
                }

                $pdo->prepare("
                    INSERT INTO ACCOUNT_ARCHIVE_NOTICE (GardenerID, AdminID, Reason, Details)
                    VALUES (?, ?, ?, ?)
                ")->execute([$gardenerId, (int) $user['id'], $reason, $details]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }

            respond(['ok' => true]);
        }

        case 'enable_account': {
            requireJsonRole('admin');
            $table = $_POST['table'] ?? '';
            $id = $_POST['id'] ?? '';
            $map = [
                'gardener' => ['COMMUNITY_GARDENER', 'GardenerID'],
                'coordinator' => ['GARDEN_COORDINATOR', 'CoordID'],
                'admin' => ['SYSTEM_ADMINISTRATOR', 'AdminID'],
            ];
            if (!isset($map[$table]) || !ctype_digit((string)$id)) respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            [$tbl, $col] = $map[$table];
            $stmt = $pdo->prepare("UPDATE $tbl SET Status = 'Active', FailedLoginAttempts = 0 WHERE $col = ? AND Status = 'Disabled'");
            $stmt->execute([(int)$id]);
            if ($stmt->rowCount() === 0) respond(['ok' => false, 'error' => 'This account is not locked.'], 409);
            respond(['ok' => true]);
        }

        case 'user_archive_details': {
            requireJsonRole('admin');
            $table = $_GET['table'] ?? '';
            $id = (int)($_GET['id'] ?? 0);

            if (!$id || !in_array($table, ['gardener', 'coordinator', 'admin'])) {
                respond(['ok' => false, 'error' => 'Invalid request.']);
            }

            $details = ['profile' => [], 'plots' => [], 'listings' => [], 'borrowed' => []];

            if ($table === 'gardener') {
                $details['archiveNotice'] = null;
                $stmt = $pdo->prepare("SELECT Name, Email, Age, Location FROM COMMUNITY_GARDENER WHERE GardenerID = ?");
                $stmt->execute([$id]);
                $details['profile'] = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("
                    SELECT Reason, Details
                    FROM ACCOUNT_ARCHIVE_NOTICE
                    WHERE GardenerID = ?
                    ORDER BY CreatedAt DESC, NoticeID DESC
                    LIMIT 1
                ");
                $stmt->execute([$id]);
                $details['archiveNotice'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                $stmt = $pdo->prepare("SELECT Label FROM PLOT WHERE GardenerID = ? AND Status = 'Occupied'");
                $stmt->execute([$id]);
                $details['plots'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $stmt = $pdo->prepare("SELECT ProduceName FROM EXCHANGE_BOARD WHERE GardenerID = ? AND Status = 'Active'");
                $stmt->execute([$id]);
                $details['listings'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $stmt = $pdo->prepare("
                    SELECT R.Name, T.Qty 
                    FROM RESOURCE_TXN T 
                    JOIN RESOURCE R ON T.ResourceID = R.ResourceID 
                    WHERE T.GardenerID = ? AND T.Status IN ('Approved', 'Return Requested')
                ");
                $stmt->execute([$id]);
                $details['borrowed'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($table === 'coordinator') {
                // Coordinators do not have an Age column in the schema!
                $stmt = $pdo->prepare("SELECT Name, Email, Shift, Location FROM GARDEN_COORDINATOR WHERE CoordID = ?");
                $stmt->execute([$id]);
                $details['profile'] = $stmt->fetch(PDO::FETCH_ASSOC);

            } elseif ($table === 'admin') {
                $stmt = $pdo->prepare("SELECT Name, Email, Age, Location FROM SYSTEM_ADMINISTRATOR WHERE AdminID = ?");
                $stmt->execute([$id]);
                $details['profile'] = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            respond(['ok' => true, 'details' => $details]);
        }

        case 'archived_accounts': {
            requireJsonRole('admin');
            $gardeners = $pdo->query("
                SELECT G.GardenerID AS id, G.Name, G.Email, 'Customer' AS Role,
                       COALESCE(NULLIF(G.Location, ''), 'Not provided') AS Location,
                       '—' AS Shift, N.Reason AS ArchiveReason, N.Details AS ArchiveDetails
                FROM COMMUNITY_GARDENER G
                LEFT JOIN ACCOUNT_ARCHIVE_NOTICE N ON N.NoticeID = (
                    SELECT MAX(N2.NoticeID)
                    FROM ACCOUNT_ARCHIVE_NOTICE N2
                    WHERE N2.GardenerID = G.GardenerID
                )
                WHERE G.Status = 'Archived'
            ");
            $coords = $pdo->query("SELECT CoordID AS id, Name, Email, 'Staff' as Role, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location, Shift FROM GARDEN_COORDINATOR WHERE Status = 'Archived'");
            $admins = $pdo->query("SELECT AdminID AS id, Name, Email, 'Admin' as Role, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location, '—' as Shift FROM SYSTEM_ADMINISTRATOR WHERE Status = 'Archived'");
            
            $all = array_merge($gardeners->fetchAll(PDO::FETCH_ASSOC), $coords->fetchAll(PDO::FETCH_ASSOC), $admins->fetchAll(PDO::FETCH_ASSOC));
            respond(['ok' => true, 'accounts' => $all]);
        }

        case 'unarchive_account': {
            requireJsonRole('admin');
            $role = $_POST['role'] ?? '';
            $id = (int)($_POST['id'] ?? 0);
            
            $map = ['Customer' => ['COMMUNITY_GARDENER', 'GardenerID'], 'Staff' => ['GARDEN_COORDINATOR', 'CoordID'], 'Admin' => ['SYSTEM_ADMINISTRATOR', 'AdminID']];
            if (!isset($map[$role]) || !$id) respond(['ok' => false, 'error' => 'Invalid request.'], 422);

            [$tbl, $col] = $map[$role];
            $pdo->prepare("UPDATE $tbl SET Status = 'Active', FailedLoginAttempts = 0 WHERE $col = ?")->execute([$id]);
            respond(['ok' => true]);
        }

        case 'dashboard_charts': {
            requireJsonRole('admin');
            
            // 1. Plot Utilization
            $plots = [
                'Occupied' => (int) $pdo->query("SELECT COUNT(*) FROM PLOT WHERE Status = 'Occupied'")->fetchColumn(),
                'Available' => (int) $pdo->query("SELECT COUNT(*) FROM PLOT WHERE Status = 'Available'")->fetchColumn(),
                'Pending' => (int) $pdo->query("SELECT COUNT(*) FROM PLOT_APPLICATION WHERE Status = 'Pending'")->fetchColumn()
            ];

            // 2. Exchange Market
            $exchange = [
                'Active' => (int) $pdo->query("SELECT COUNT(*) FROM EXCHANGE_LISTING WHERE ListingID NOT IN (SELECT ListingID FROM EXCHANGE_ORDER)")->fetchColumn(),
                'Completed' => (int) $pdo->query("SELECT COUNT(*) FROM EXCHANGE_ORDER")->fetchColumn()
            ];

            // 3. Resource Inventory (Per Item)
            $resourcesRaw = $pdo->query("
                SELECT R.ResourceID, R.Name, R.TotalQty,
                       GREATEST(0, R.TotalQty - COALESCE(SUM(CASE
                           WHEN T.Status IN ('Approved', 'Return Requested') THEN T.Qty
                           ELSE 0
                       END), 0)) AS AvailableQty
                FROM RESOURCE R
                LEFT JOIN RESOURCE_TXN T ON T.ResourceID = R.ResourceID
                GROUP BY R.ResourceID, R.Name, R.TotalQty
                ORDER BY R.Name
            ")->fetchAll(PDO::FETCH_ASSOC);
            $resources = [ 'labels' => [], 'available' => [], 'borrowed' => [] ];
            
            foreach ($resourcesRaw as $r) {
                $resources['labels'][] = $r['Name'];
                $resources['available'][] = (int)$r['AvailableQty'];
                // Borrowed is Total minus Available
                $resources['borrowed'][] = (int)$r['TotalQty'] - (int)$r['AvailableQty'];
            }

            respond(['ok' => true, 'plots' => $plots, 'exchange' => $exchange, 'resources' => $resources]);
        }

        default:
            respond(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    // This will print the exact SQL or PHP error to your screen
    respond(['ok' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
}
