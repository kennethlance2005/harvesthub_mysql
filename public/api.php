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
 *    POST action=add_coordinator      { name, email, password, shift }
 *    POST action=delete_account       { table: gardener|coordinator, id }
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
    if (!$user || $user['role'] !== $role) {
        respond(['ok' => false, 'error' => 'Not authorized.'], 403);
    }
    return $user;
}

function syncLegacyCommunityPlots(PDO $pdo): void {
    $pdo->exec("
        INSERT INTO PLOT (Label, GardenerID, Status)
        SELECT CP.PlotName,
               CASE WHEN CP.Status = 'Occupied' AND G.GardenerID IS NOT NULL THEN G.GardenerID ELSE NULL END,
               CASE WHEN CP.Status = 'Occupied' AND G.GardenerID IS NOT NULL THEN 'Occupied' ELSE 'Available' END
        FROM COMMUNITY_PLOTS CP
        LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = CP.OccupantID
        WHERE NOT EXISTS (
            SELECT 1 FROM PLOT P WHERE LOWER(P.Label) = LOWER(CP.PlotName)
        )
    ");
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
function mergeOrCreateReturnRequestRow(PDO $pdo, int $gardenerId, int $resourceId, int $qty, int $coordId, ?int $pltId): void {
    $existing = $pdo->prepare("SELECT TxnID FROM RESOURCE_TXN WHERE GardenerID = ? AND ResourceID = ? AND Status = 'Return Requested' FOR UPDATE");
    $existing->execute([$gardenerId, $resourceId]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $pdo->prepare("UPDATE RESOURCE_TXN SET Qty = Qty + ?, ReturnRequestedAt = NOW(), CoordID = ? WHERE TxnID = ?")
            ->execute([$qty, $coordId, (int) $existingId]);
    } else {
        $pdo->prepare("INSERT INTO RESOURCE_TXN (GardenerID, CoordID, ResourceID, PltID, Qty, Status, ApprovedAt, ReturnRequestedAt) VALUES (?, ?, ?, ?, ?, 'Return Requested', NOW(), NOW())")
            ->execute([$gardenerId, $coordId, $resourceId, $pltId, $qty]);
    }
}

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

            // 1. Check if the user is a Community Gardener
            $stmt = $pdo->prepare("SELECT GardenerID as id, Name, PasswordHash FROM COMMUNITY_GARDENER WHERE Email = ? AND Status = 'Active'");
            $stmt->execute([$email]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $userRecord = $row;
                $role = 'customer';
            }

            // 2. Check if the user is a Garden Coordinator
            if (!$userRecord) {
                $stmt = $pdo->prepare("SELECT CoordID as id, Name, PasswordHash FROM GARDEN_COORDINATOR WHERE Email = ? AND Status = 'Active'");
                $stmt->execute([$email]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $userRecord = $row;
                    $role = 'staff';
                }
            }

            // 3. Check if the user is a System Administrator
            if (!$userRecord) {
                $stmt = $pdo->prepare("SELECT AdminID as id, Name, PasswordHash FROM SYSTEM_ADMINISTRATOR WHERE Email = ? AND Status = 'Active'");
                $stmt->execute([$email]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $userRecord = $row;
                    $role = 'admin';
                }
            }

            if (!$userRecord || !password_verify($password, $userRecord['PasswordHash'])) {
                respond(['ok' => false, 'error' => 'Invalid email or password.'], 401);
            }

            $_SESSION['user'] = [
                'role' => $role,
                'id' => (int) $userRecord['id'],
                'name' => $userRecord['Name'],
            ];

            // If checked, save the email. If unchecked, delete the cookie.
            if (($_POST['remember'] ?? '0') === '1') {
                setcookie('remembered_email', $email, time() + (86400 * 30), '/');
            } else {
                setcookie('remembered_email', '', time() - 3600, '/');
            }

            respond(['ok' => true, 'redirect' => loginRedirectFor($role)]);

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
            
            // Automatically determine role based on email domain
            $role = 'customer'; // Default role
            if (str_ends_with(strtolower($email), '@staff.harvesthub.com')) {
                $role = 'staff';
            }

            // Set a default shift for coordinators
            $shift = 'Morning';

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

            $pdo->prepare("
                INSERT INTO SIGNUP_REQUEST (FirstName, LastName, Age, Location, Email, PasswordHash, Role, Shift)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8'),
                (int) $age,
                htmlspecialchars($location, ENT_QUOTES, 'UTF-8'),
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                $role,
                $shift,
            ]);
            respond(['ok' => true]);
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

            $plot = $pdo->prepare('SELECT PltID FROM PLOT WHERE PltID = ? AND GardenerID = ? AND Status = \'Occupied\'');
            $plot->execute([(int) $plotId, $user['id']]);
            if (!$plot->fetchColumn()) {
                respond(['ok' => false, 'error' => 'That plot is not assigned to you.'], 409);
            }

            $pending = $pdo->prepare("SELECT 1 FROM PLOT_APPLICATION WHERE GardenerID = ? AND PltID = ? AND Status = 'Pending'");
            $pending->execute([$user['id'], (int) $plotId]);
            if ($pending->fetchColumn()) {
                respond(['ok' => false, 'error' => 'An unassignment request is already pending.'], 409);
            }

            $pdo->prepare("INSERT INTO PLOT_APPLICATION (GardenerID, PltID, Status, RequestType) VALUES (?, ?, 'Pending', 'Unassign')")
                ->execute([$user['id'], (int) $plotId]);
            respond(['ok' => true]);
        }

        case 'apply_plot': {
            $user = requireJsonRole('customer');
            $pltId = $_POST['plt_id'] ?? '';
            if (!ctype_digit((string) $pltId)) respond(['ok' => false, 'error' => 'Invalid plot.'], 422);

            $check = $pdo->prepare("SELECT Status FROM PLOT WHERE PltID = ?");
            $check->execute([(int) $pltId]);
            $status = $check->fetchColumn();
            if ($status !== 'Available') respond(['ok' => false, 'error' => 'That plot is no longer available.'], 409);

            $pdo->prepare("INSERT INTO PLOT_APPLICATION (GardenerID, PltID, Status, RequestType) VALUES (?, ?, 'Pending', 'Apply')")
                ->execute([$user['id'], (int) $pltId]);
            respond(['ok' => true]);
        }

        // ---------------- CUSTOMER: Crop Log ----------------

        case 'my_croplog': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("
                SELECT L.LogID, L.CropName, L.MaintenanceNotes, L.HarvestYield, L.LoggedAt, P.Label
                FROM CROP_LOG L JOIN PLOT P ON P.PltID = L.PltID
                WHERE L.GardenerID = ? ORDER BY L.LoggedAt DESC
            ");
            $stmt->execute([$user['id']]);
            respond(['ok' => true, 'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'croplog_create': {
            $user = requireJsonRole('customer');
            $plot = $pdo->prepare("SELECT PltID FROM PLOT WHERE GardenerID = ?");
            $plot->execute([$user['id']]);
            $pltId = $plot->fetchColumn();
            if (!$pltId) respond(['ok' => false, 'error' => 'You need an assigned plot before logging crops.'], 409);

            $crop = trim($_POST['crop_name'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $yield = trim($_POST['yield'] ?? '');

            if ($crop === '' || mb_strlen($crop) > 60) respond(['ok' => false, 'error' => 'Crop name is required.'], 422);
            if (mb_strlen($notes) > 300 || mb_strlen($yield) > 60) respond(['ok' => false, 'error' => 'Notes or yield too long.'], 422);

            $pdo->prepare("INSERT INTO CROP_LOG (GardenerID, PltID, CropName, MaintenanceNotes, HarvestYield) VALUES (?, ?, ?, ?, ?)")
                ->execute([$user['id'], $pltId, htmlspecialchars($crop, ENT_QUOTES, 'UTF-8'), htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'), htmlspecialchars($yield, ENT_QUOTES, 'UTF-8')]);
            respond(['ok' => true]);
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
            $item = trim($_POST['item'] ?? '');
            $qty = trim($_POST['qty'] ?? '');
            $desc = trim($_POST['desc'] ?? '');

            if (empty($item) || empty($qty)) {
                respond(['ok' => false, 'error' => 'Item and Quantity are required.'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO EXCHANGE_BOARD (GardenerID, ProduceName, Qty, Description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], $item, $qty, $desc]);
            
            if ($stmt->rowCount() > 0) {
                respond(['ok' => true]);
            } else {
                respond(['ok' => false, 'error' => 'Failed to create post.'], 400);
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
            requireJsonRole('customer');
            $rows = $pdo->query("
                SELECT R.ResourceID, R.Name, R.TotalQty,
                       GREATEST(0, R.TotalQty - COALESCE((
                           SELECT SUM(T.Qty) FROM RESOURCE_TXN T
                           WHERE T.ResourceID = R.ResourceID AND T.Status IN ('Approved', 'Return Requested')
                       ), 0)) AS AvailableQty
                FROM RESOURCE R ORDER BY R.Name
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'resources' => $rows]);
        }

        case 'resource_request': {
            $user = requireJsonRole('customer');
            $resourceId = $_POST['resource_id'] ?? '';
            $qty = $_POST['qty'] ?? '';
            if (!ctype_digit((string) $resourceId) || !ctype_digit((string) $qty) || (int) $qty < 1) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $pdo->beginTransaction();

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

            // If this gardener already has a pending request for the same resource,
            // combine the new quantity into it instead of creating a second request.
            $existing = $pdo->prepare("SELECT TxnID FROM RESOURCE_TXN WHERE GardenerID = ? AND ResourceID = ? AND Status = 'Requested' FOR UPDATE");
            $existing->execute([$user['id'], (int) $resourceId]);
            $existingTxnId = $existing->fetchColumn();

            if ($existingTxnId) {
                $pdo->prepare("UPDATE RESOURCE_TXN SET Qty = Qty + ? WHERE TxnID = ?")
                    ->execute([(int) $qty, (int) $existingTxnId]);
            } else {
                $pdo->prepare("INSERT INTO RESOURCE_TXN (GardenerID, ResourceID, Qty, Status) VALUES (?, ?, ?, 'Requested')")
                    ->execute([$user['id'], (int) $resourceId, (int) $qty]);
            }
            $pdo->commit();
            respond(['ok' => true]);
        }

        case 'my_resource_requests': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("
                SELECT T.TxnID, R.Name, T.Qty, T.Status, T.RequestedAt, T.ApprovedAt, T.ReturnRequestedAt
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
            $stmt = $pdo->prepare("SELECT ResourceID, Qty, Status FROM RESOURCE_TXN WHERE TxnID = ? AND GardenerID = ? FOR UPDATE");
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
            $stmt = $pdo->prepare("SELECT ItemID, ItemName, Qty, AddedAt FROM PERSONAL_INVENTORY WHERE GardenerID = ? ORDER BY AddedAt DESC");
            $stmt->execute([$user['id']]);
            respond(['ok' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'add_personal_item': {
            $user = requireJsonRole('customer');
            $name = trim($_POST['item_name'] ?? '');
            $qty = (int)($_POST['qty'] ?? 1);

            if (empty($name) || $qty < 1) respond(['ok' => false, 'error' => 'Invalid item data.'], 422);

            $stmt = $pdo->prepare("INSERT INTO PERSONAL_INVENTORY (GardenerID, ItemName, Qty) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $name, $qty]);
            respond(['ok' => true]);
        }

        case 'remove_personal_item': {
            $user = requireJsonRole('customer');
            $itemId = (int)($_POST['item_id'] ?? 0);

            $stmt = $pdo->prepare("DELETE FROM PERSONAL_INVENTORY WHERE ItemID = ? AND GardenerID = ?");
            $stmt->execute([$itemId, $user['id']]);
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
                SELECT CropName, MaintenanceNotes, HarvestYield, LoggedAt 
                FROM CROP_LOG 
                WHERE GardenerID = ? 
                ORDER BY LoggedAt DESC 
                LIMIT 4
            ");
            $logsStmt->execute([$user['id']]);
            $recentLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. New on Exchange (last 4 active listings)
            $exchangeStmt = $pdo->query("
                SELECT ProduceName, Qty, Description, CreatedAt 
                FROM EXCHANGE_BOARD 
                WHERE Status = 'Active' 
                ORDER BY CreatedAt DESC 
                LIMIT 4
            ");
            $recentExchange = $exchangeStmt->fetchAll(PDO::FETCH_ASSOC);

            respond([
                'ok' => true,
                'stats' => [
                    'active_plots' => $activePlots,
                    'pending_resources' => $pendingResources,
                    'my_listings' => $myListings
                ],
                'recent_logs' => $recentLogs,
                'recent_exchange' => $recentExchange
            ]);
        }

        // ---------------- STAFF ----------------

        case 'pending_applications': {
            requireJsonRole('staff');
            $rows = $pdo->query("
                SELECT PA.AppID,
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
            respond(['ok' => true, 'applications' => $rows]);
        }

        case 'process_application': {
            $user = requireJsonRole('staff');
            $appId = $_POST['app_id'] ?? '';
            $decision = $_POST['decision'] ?? '';
            if (!ctype_digit((string) $appId) || !in_array($decision, ['approve', 'reject'], true)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $app = $pdo->prepare("SELECT GardenerID, PltID, Status, RequestType FROM PLOT_APPLICATION WHERE AppID = ?");
            $app->execute([(int) $appId]);
            $row = $app->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['Status'] !== 'Pending') respond(['ok' => false, 'error' => 'Application already processed.'], 409);

            $newStatus = $decision === 'approve' ? 'Approved' : 'Rejected';
            $pdo->prepare("UPDATE PLOT_APPLICATION SET Status = ?, CoordID = ? WHERE AppID = ?")
                ->execute([$newStatus, $user['id'], (int) $appId]);

            $plotLabel = $pdo->prepare("SELECT Label FROM PLOT WHERE PltID = ?");
            $plotLabel->execute([$row['PltID']]);
            $plotName = $plotLabel->fetchColumn();

            if ($decision === 'approve') {
                if ($row['RequestType'] === 'Unassign') {
                    $pdo->prepare("UPDATE PLOT SET GardenerID = NULL, Status = 'Available' WHERE PltID = ? AND GardenerID = ?")
                        ->execute([$row['PltID'], $row['GardenerID']]);
                    if ($plotName) {
                        $pdo->prepare("UPDATE community_plots SET Status = 'Available', OccupantID = NULL WHERE PlotName = ?")
                            ->execute([$plotName]);
                    }
                } else {
                    $pdo->prepare("UPDATE PLOT SET GardenerID = ?, Status = 'Occupied' WHERE PltID = ?")
                        ->execute([$row['GardenerID'], $row['PltID']]);
                    if ($plotName) {
                        $pdo->prepare("UPDATE community_plots SET Status = 'Occupied', OccupantID = ? WHERE PlotName = ?")
                            ->execute([$row['GardenerID'], $plotName]);
                    }
                }
            } else {
                if ($plotName) {
                    $pdo->prepare("UPDATE community_plots SET Status = 'Available', OccupantID = NULL WHERE PlotName = ?")
                        ->execute([$plotName]);
                }
            }
            respond(['ok' => true]);
        }

        case 'pending_resource_txns': {
            requireJsonRole('staff');
            $rows = $pdo->query("
                SELECT T.TxnID, G.Name AS GardenerName, R.Name AS ResourceName, T.Qty, T.RequestedAt
                FROM RESOURCE_TXN T
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID
                JOIN RESOURCE R ON R.ResourceID = T.ResourceID
                WHERE T.Status = 'Requested' ORDER BY T.RequestedAt ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'transactions' => $rows]);
        }

        case 'add_resource': {
            requireJsonRole('staff');
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
                $pdo->prepare('UPDATE RESOURCE SET TotalQty = TotalQty + ?, AvailableQty = AvailableQty + ? WHERE ResourceID = ?')
                    ->execute([(int) $qty, (int) $qty, (int) $existingId]);
            } else {
                $pdo->prepare('INSERT INTO RESOURCE (Name, TotalQty, AvailableQty) VALUES (?, ?, ?)')
                    ->execute([$name, (int) $qty, (int) $qty]);
            }
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
            $txn = $pdo->prepare("SELECT GardenerID, ResourceID, Qty, Status FROM RESOURCE_TXN WHERE TxnID = ? FOR UPDATE");
            $txn->execute([(int) $txnId]);
            $row = $txn->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['Status'] !== 'Requested') {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'Already processed.'], 409);
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

            if ($decision === 'approve') {
                $res = $pdo->prepare('SELECT TotalQty FROM RESOURCE WHERE ResourceID = ? FOR UPDATE');
                $res->execute([$row['ResourceID']]);
                $totalQty = $res->fetchColumn();
                if ($totalQty === false) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Resource not found.'], 404);
                }
                $active = $pdo->prepare("SELECT COALESCE(SUM(Qty), 0) FROM RESOURCE_TXN WHERE ResourceID = ? AND Status IN ('Approved', 'Return Requested')");
                $active->execute([(int) $row['ResourceID']]);
                $availableQty = max(0, (int) $totalQty - (int) $active->fetchColumn());
                if ($chosenQty > $availableQty) {
                    $pdo->rollBack();
                    respond(['ok' => false, 'error' => 'Not enough stock left to approve that many.'], 409);
                }

                $plot = $pdo->prepare("SELECT PltID FROM PLOT WHERE GardenerID = ? AND Status = 'Occupied' ORDER BY PltID LIMIT 1");
                $plot->execute([(int) $row['GardenerID']]);
                $plotId = $plot->fetchColumn();
                $plotId = $plotId === false ? null : (int) $plotId;

                // Fold the approved amount into the gardener's existing borrower
                // assignment for this resource rather than adding a new row.
                mergeOrCreateApprovalRow($pdo, (int) $row['GardenerID'], (int) $row['ResourceID'], $chosenQty, (int) $user['id'], $plotId);

                if ($remainder > 0) {
                    // Leave the rest of the request pending — don't reject it.
                    $pdo->prepare('UPDATE RESOURCE_TXN SET Qty = ? WHERE TxnID = ?')
                        ->execute([$remainder, (int) $txnId]);
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
                    $pdo->prepare("INSERT INTO RESOURCE_TXN (GardenerID, CoordID, ResourceID, Qty, Status) VALUES (?, ?, ?, ?, 'Rejected')")
                        ->execute([(int) $row['GardenerID'], (int) $user['id'], (int) $row['ResourceID'], $chosenQty]);
                } else {
                    $pdo->prepare("UPDATE RESOURCE_TXN SET Status = 'Rejected', CoordID = ? WHERE TxnID = ?")
                        ->execute([$user['id'], (int) $txnId]);
                }
            }
            $pdo->commit();
            respond(['ok' => true]);
        }

        case 'create_plot': {
            requireJsonRole('staff');
            $label = trim($_POST['label'] ?? '');
            if ($label === '' || strlen($label) > 80) {
                respond(['ok' => false, 'error' => 'Enter a plot name up to 80 characters.'], 422);
            }
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/', $label)) {
                respond(['ok' => false, 'error' => 'Plot names may use letters, numbers, spaces, dots, hyphens, and underscores.'], 422);
            }

            $duplicate = $pdo->prepare('SELECT 1 FROM PLOT WHERE LOWER(Label) = LOWER(?) LIMIT 1');
            $duplicate->execute([$label]);  
            if ($duplicate->fetchColumn()) {
                respond(['ok' => false, 'error' => 'A plot with that name already exists.'], 409);
            }

            $pdo->prepare("INSERT INTO PLOT (Label, GardenerID, Status) VALUES (?, NULL, 'Available')")
                ->execute([$label]);
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
            requireJsonRole('staff');
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

        case 'resource_records': {
            requireJsonRole('staff');
            $rows = $pdo->query("
                SELECT T.TxnID, R.Name AS ResourceName, G.Name AS GardenerName,
                       COALESCE(AssignedPlot.Label, CurrentPlot.Label) AS PlotLabel,
                       'Borrowed' AS Action, COALESCE(T.ApprovedAt, T.RequestedAt) AS OccurredAt
                FROM RESOURCE_TXN T
                JOIN RESOURCE R ON R.ResourceID = T.ResourceID
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID
                LEFT JOIN PLOT AssignedPlot ON AssignedPlot.PltID = T.PltID
                LEFT JOIN PLOT CurrentPlot ON CurrentPlot.PltID = (
                    SELECT MIN(P2.PltID) FROM PLOT P2
                    WHERE P2.GardenerID = T.GardenerID AND P2.Status = 'Occupied'
                )
                WHERE T.Status IN ('Approved', 'Return Requested', 'Returned')
                UNION ALL
                SELECT T.TxnID, R.Name AS ResourceName, G.Name AS GardenerName,
                       COALESCE(AssignedPlot.Label, CurrentPlot.Label) AS PlotLabel,
                       'Returned' AS Action, COALESCE(T.ReturnedAt, T.RequestedAt) AS OccurredAt
                FROM RESOURCE_TXN T
                JOIN RESOURCE R ON R.ResourceID = T.ResourceID
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID
                LEFT JOIN PLOT AssignedPlot ON AssignedPlot.PltID = T.PltID
                LEFT JOIN PLOT CurrentPlot ON CurrentPlot.PltID = (
                    SELECT MIN(P2.PltID) FROM PLOT P2
                    WHERE P2.GardenerID = T.GardenerID AND P2.Status = 'Occupied'
                )
                WHERE T.Status = 'Returned'
                ORDER BY OccurredAt DESC, TxnID DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'records' => $rows]);
        }

        case 'request_resource_return': {
            $user = requireJsonRole('staff');
            $txnId = $_POST['txn_id'] ?? '';
            if (!ctype_digit((string) $txnId)) {
                respond(['ok' => false, 'error' => 'Invalid transaction.'], 422);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT GardenerID, ResourceID, PltID, Qty, Status FROM RESOURCE_TXN WHERE TxnID = ? FOR UPDATE");
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
            mergeOrCreateReturnRequestRow($pdo, (int) $row['GardenerID'], (int) $row['ResourceID'], $returnQty, (int) $user['id'], $pltId);

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
            $crop = trim($_POST['crop_name'] ?? '');
            $planted = $_POST['planted_date'] ?? '';
            $notes = trim($_POST['notes'] ?? '');
            $harvest = $_POST['est_harvest_date'] ?? null;

            if (empty($crop) || empty($planted)) {
                respond(['ok' => false, 'error' => 'Crop name and planted date are required.'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO GARDEN_PLOTS (GardenerID, CropName, PlantedDate, EstHarvestDate, Notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $crop, $planted, $harvest === '' ? null : $harvest, $notes]);
            respond(['ok' => true]);
        }

        case 'update_crop_status': {
            $user = requireJsonRole('customer');
            $plotId = (int)($_POST['plot_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if (!in_array($status, ['Planted', 'Growing', 'Harvested', 'Failed'])) {
                respond(['ok' => false, 'error' => 'Invalid status.'], 422);
            }

            $stmt = $pdo->prepare("UPDATE GARDEN_PLOTS SET Status = ? WHERE PlotID = ? AND GardenerID = ?");
            $stmt->execute([$status, $plotId, $user['id']]);
            respond(['ok' => true]);
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
                                             ) AS UnassignmentPending
                                FROM PLOT P
                                LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = P.GardenerID
                                ORDER BY P.Label ASC
                        ");
                        $stmt->execute([$user['id'], $user['id']]);
                        $plots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            respond(['ok' => true, 'plots' => $plots]);
        }

        case 'request_garden_plot': {
            $user = requireJsonRole('customer');
            $plotId = (int)($_POST['plot_id'] ?? 0);

            $check = $pdo->prepare("
                SELECT P.PltID, P.Label,
                       CASE
                         WHEN P.Status = 'Occupied' OR P.GardenerID IS NOT NULL THEN 'Occupied'
                         WHEN P.Status = 'Pending Approval' OR EXISTS (
                           SELECT 1 FROM PLOT_APPLICATION PA
                           WHERE PA.PltID = P.PltID AND PA.Status = 'Pending' AND PA.RequestType = 'Apply'
                         ) THEN 'Pending Approval'
                         ELSE 'Available'
                       END AS MapStatus
                FROM PLOT P WHERE P.PltID = ?
            ");
            $check->execute([$plotId]);
            $plot = $check->fetch(PDO::FETCH_ASSOC);

            if (!$plot || $plot['MapStatus'] !== 'Available') {
                respond(['ok' => false, 'error' => 'This plot is no longer available.']);
            }

            $existing = $pdo->prepare("SELECT 1 FROM PLOT_APPLICATION WHERE PltID = ? AND Status = 'Pending' AND RequestType = 'Apply' LIMIT 1");
            $existing->execute([$plot['PltID']]);
            if ($existing->fetchColumn()) {
                respond(['ok' => false, 'error' => 'This plot already has a pending request.'], 409);
            }

            $pdo->prepare("INSERT INTO PLOT_APPLICATION (GardenerID, PltID, Status, RequestType) VALUES (?, ?, 'Pending', 'Apply')")
                ->execute([$user['id'], $plot['PltID']]);

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
                'active_listings' => $count("SELECT COUNT(*) FROM EXCHANGE_LISTING WHERE ListingID NOT IN (SELECT ListingID FROM EXCHANGE_ORDER)"),
                'completed_trades' => $count("SELECT COUNT(*) FROM EXCHANGE_ORDER"),
            ]]);
        }

        case 'accounts': {
            $user = requireJsonRole('admin');
            $gardeners = $pdo->query("SELECT GardenerID AS id, Name, Email, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location FROM COMMUNITY_GARDENER WHERE Status = 'Active' ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
            $coordinators = $pdo->query("SELECT CoordID AS id, Name, Email, Shift, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location FROM GARDEN_COORDINATOR WHERE Status = 'Active' ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
            $admins = $pdo->query("SELECT AdminID AS id, Name, Email, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location FROM SYSTEM_ADMINISTRATOR WHERE Status = 'Active' ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
            
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

        case 'process_signup': {
            $user = requireJsonRole('admin');
            $requestId = $_POST['request_id'] ?? '';
            $decision = $_POST['decision'] ?? '';
            if (!ctype_digit((string) $requestId) || !in_array($decision, ['approve', 'reject'], true)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $stmt = $pdo->prepare("SELECT * FROM SIGNUP_REQUEST WHERE RequestID = ?");
            $stmt->execute([(int) $requestId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['Status'] !== 'Pending') respond(['ok' => false, 'error' => 'Already processed.'], 409);

            if ($decision === 'approve') {
                // Re-check the email hasn't been taken since the request came in.
                $table = $row['Role'] === 'staff' ? 'GARDEN_COORDINATOR' : 'COMMUNITY_GARDENER';
                $dupe = $pdo->prepare("SELECT 1 FROM $table WHERE Email = ?");
                $dupe->execute([$row['Email']]);
                if ($dupe->fetchColumn()) {
                    respond(['ok' => false, 'error' => 'That email is already in use.'], 409);
                }

                $name = trim($row['FirstName'] . ' ' . $row['LastName']);
                try {
                    if ($row['Role'] === 'staff') {
                        $pdo->prepare("INSERT INTO GARDEN_COORDINATOR (Name, Email, PasswordHash, Shift, Location) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$name, $row['Email'], $row['PasswordHash'], $row['Shift'], $row['Location']]);
                    } else {
                        $pdo->prepare("INSERT INTO COMMUNITY_GARDENER (Name, Email, PasswordHash, Age, Location) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$name, $row['Email'], $row['PasswordHash'], $row['Age'], $row['Location']]);
                    }
                } catch (PDOException $e) {
                    respond(['ok' => false, 'error' => 'That email is already in use.'], 409);
                }
            }

            $newStatus = $decision === 'approve' ? 'Approved' : 'Rejected';
            $pdo->prepare("UPDATE SIGNUP_REQUEST SET Status = ?, ReviewedAt = NOW(), ReviewedBy = ? WHERE RequestID = ?")
                ->execute([$newStatus, $user['id'], (int) $requestId]);

            respond(['ok' => true]);
        }

        case 'add_coordinator': {
            requireJsonRole('admin');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $shift = trim($_POST['shift'] ?? 'Morning');
            $location = trim($_POST['location'] ?? 'Not provided');

            $errors = [];
            if ($name === '' || mb_strlen($name) > 80) $errors[] = 'Name is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
            if (mb_strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
            if (!in_array($shift, ['Morning', 'Afternoon', 'Evening'], true)) $errors[] = 'Invalid shift.';
            if ($errors) respond(['ok' => false, 'errors' => $errors], 422);

            try {
                $pdo->prepare("INSERT INTO GARDEN_COORDINATOR (Name, Email, PasswordHash, Shift, Location) VALUES (?, ?, ?, ?, ?)")
                    ->execute([htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $email, password_hash($password, PASSWORD_BCRYPT), $shift, $location]);
            } catch (PDOException $e) {
                respond(['ok' => false, 'error' => 'That email is already in use.'], 409);
            }
            respond(['ok' => true]);
        }

        case 'create_admin': {
            requireJsonRole('admin');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $age = $_POST['age'] ?? '';
            $location = trim($_POST['location'] ?? '');
            $password = $_POST['password'] ?? '';

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
            $pdo->prepare("UPDATE $tbl SET Status = 'Archived' WHERE $col = ?")->execute([$idNum]);
            
            if ($table === 'gardener') {
                $pdo->prepare("UPDATE PLOT SET GardenerID = NULL, Status = 'Available' WHERE GardenerID = ?")->execute([$idNum]);
            }

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
                $stmt = $pdo->prepare("SELECT Name, Email, Age, Location FROM COMMUNITY_GARDENER WHERE GardenerID = ?");
                $stmt->execute([$id]);
                $details['profile'] = $stmt->fetch(PDO::FETCH_ASSOC);

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
                    WHERE T.GardenerID = ? AND T.Status = 'Approved'
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
            $gardeners = $pdo->query("SELECT GardenerID AS id, Name, Email, 'Customer' as Role, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location, '—' as Shift FROM COMMUNITY_GARDENER WHERE Status = 'Archived'");
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
            $pdo->prepare("UPDATE $tbl SET Status = 'Active' WHERE $col = ?")->execute([$id]);
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