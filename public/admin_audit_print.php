<?php
// Printable audit log (the "Save as PDF" button on the Audit Log page).
// Uses the same filters as the page; the browser's print dialog saves it as a PDF.
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
if (!can('reports.export')) showNoAccessPage($user);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit.php';
$pdo = getDb();

const AUDIT_PRINT_LIMIT = 2000;
[$where, $params] = auditLogFilters($_GET);
$count = $pdo->prepare("SELECT COUNT(*) FROM AUDIT_LOG $where");
$count->execute($params);
$total = (int) $count->fetchColumn();
$rows = $pdo->prepare("
    SELECT OccurredAt, ActorName, ActorType, Module, Summary, Reason
    FROM AUDIT_LOG $where
    ORDER BY OccurredAt DESC, LogID DESC
    LIMIT " . AUDIT_PRINT_LIMIT);
$rows->execute($params);
$entries = $rows->fetchAll(PDO::FETCH_ASSOC);
// The database clock, so the printed time matches the entry times.
$printedAt = $pdo->query('SELECT NOW()')->fetchColumn();

$filters = array_filter([
    'search' => $_GET['q'] ?? '', 'area' => $_GET['module'] ?? '', 'who' => $_GET['actor_type'] ?? '',
    'action' => $_GET['action_type'] ?? '', 'from' => $_GET['from'] ?? '', 'to' => $_GET['to'] ?? '',
]);
$actor = auditActor(currentUser());
logAudit($pdo, 'admin', 'audit_exported', "{$actor['name']} saved the audit log as a PDF (" . count($entries) . ' entries).', [
    'actor' => $actor, 'after' => $filters + ['format' => 'PDF'],
]);

$areaLabels = ['accounts' => 'Accounts', 'roles' => 'Roles', 'plots' => 'Plots', 'resources' => 'Resources', 'exchange' => 'Exchange', 'crops' => 'Crops', 'admin' => 'Admin tools'];
$whoLabels = ['admin' => 'Administrators', 'coordinator' => 'Coordinators', 'gardener' => 'Gardeners', 'guest' => 'Visitors', 'system' => 'HarvestHub (automatic)'];
$filterText = [];
if (isset($filters['search'])) $filterText[] = 'Search: "' . $filters['search'] . '"';
if (isset($filters['area'])) $filterText[] = 'Area: ' . ($areaLabels[$filters['area']] ?? $filters['area']);
if (isset($filters['who'])) $filterText[] = 'Who: ' . ($whoLabels[$filters['who']] ?? $filters['who']);
if (isset($filters['action'])) $filterText[] = 'Action: ' . ucfirst(str_replace('_', ' ', $filters['action']));
if (isset($filters['from']) || isset($filters['to'])) {
    $filterText[] = 'Dates: ' . ($filters['from'] ?? 'start') . ' to ' . ($filters['to'] ?? 'today');
}
$h = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$who = static fn (array $e) => $e['ActorName'] ?: (['guest' => 'Someone not logged in', 'system' => 'HarvestHub'][$e['ActorType']] ?? 'Unknown');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub audit log <?= $h(substr($printedAt, 0, 10)) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=52">
</head>
<body class="audit-print">
  <div class="audit-print-toolbar">
    <button type="button" class="btn btn-accent" onclick="window.print()">Save as PDF / Print</button>
    <a class="btn btn-ghost" href="admin_audit_log.php">Back to the audit log</a>
    <span class="text-muted">In the print window, choose "Save as PDF" as the printer.</span>
  </div>

  <header class="audit-print-head">
    <p class="eyebrow">HarvestHub</p>
    <h1>Audit log</h1>
    <p>Printed <?= $h(date('F j, Y, g:i A', strtotime($printedAt))) ?> by <?= $h($actor['name']) ?>.
      <?= $total > count($entries) ? 'Showing the ' . number_format(count($entries)) . ' most recent of ' . number_format($total) . ' matching entries.' : number_format($total) . ' ' . ($total === 1 ? 'entry' : 'entries') . '.' ?></p>
    <p><?= $filterText ? $h(implode(' · ', $filterText)) : 'No filters: every entry.' ?></p>
  </header>

  <table class="audit-print-table">
    <thead><tr><th>When</th><th>Who</th><th>Area</th><th>What happened</th><th>Reason</th></tr></thead>
    <tbody>
      <?php foreach ($entries as $e): ?>
      <tr>
        <td><?= $h(date('M j, Y g:i A', strtotime($e['OccurredAt']))) ?></td>
        <td><?= $h($who($e)) ?></td>
        <td><?= $h($areaLabels[$e['Module']] ?? $e['Module']) ?></td>
        <td><?= $h($e['Summary']) ?></td>
        <td><?= $h($e['Reason'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$entries): ?>
      <tr><td colspan="5">No entries match these filters.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
