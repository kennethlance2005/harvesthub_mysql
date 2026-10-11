<?php
// Printable report (the "Print / Save as PDF" button on the Reports page).
// Builds the same report as the page; the browser's print window saves it as a PDF.
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
if (!can('reports.export')) showNoAccessPage($user);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/reports.php';
$pdo = getDb();

$key = (string) ($_GET['report'] ?? '');
if (!isset(REPORTS[$key]) || !can(REPORTS[$key][2])) showNoAccessPage($user);

$range = dashboardRange($pdo, $_GET['from'] ?? null, $_GET['to'] ?? null);
$report = reportBuild($pdo, $key, $range, ['person' => $_GET['person'] ?? '', 'module' => $_GET['module'] ?? '']);
$printedAt = $pdo->query('SELECT NOW()')->fetchColumn(); // database clock, like the report's own times
$actor = auditActor(currentUser());
logAudit($pdo, 'admin', 'report_exported', "{$actor['name']} printed the {$report['title']} report for {$report['period']} (PDF).", [
    'actor' => $actor, 'after' => ['Report' => $report['title'], 'Period' => $report['period'], 'Format' => 'PDF'],
]);
$h = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub <?= $h($report['title']) ?> report <?= $h($range['from']) ?> to <?= $h($range['to']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=60">
</head>
<body class="audit-print report-print">
  <div class="audit-print-toolbar">
    <button type="button" class="btn btn-accent" onclick="window.print()">Save as PDF / Print</button>
    <a class="btn btn-ghost" href="admin_reports.php?report=<?= $h($key) ?>">Back to reports</a>
    <span class="text-muted">In the print window, choose "Save as PDF" as the printer.</span>
  </div>

  <header class="audit-print-head">
    <p class="eyebrow">HarvestHub report</p>
    <h1><?= $h($report['title']) ?></h1>
    <p><?= $h($report['description']) ?></p>
    <p><strong>Period:</strong> <?= $h($report['period']) ?> · Printed <?= $h(date('F j, Y, g:i A', strtotime($printedAt))) ?> by <?= $h($actor['name']) ?></p>
  </header>

  <?php if ($report['summary']): ?>
  <dl class="report-print-summary">
    <?php foreach ($report['summary'] as $s): ?>
      <div><dt><?= $h($s['label']) ?></dt><dd><?= $h($s['value']) ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <?php endif; ?>

  <?php foreach ($report['tables'] as $t): ?>
  <section class="report-print-section">
    <h2><?= $h($t['title']) ?> <span class="report-count"><?= count($t['rows']) ?> <?= count($t['rows']) === 1 ? 'row' : 'rows' ?></span></h2>
    <?php if ($t['note'] !== ''): ?><p class="report-print-note"><?= $h($t['note']) ?></p><?php endif; ?>
    <table class="audit-print-table report-print-table">
      <thead><tr><?php foreach ($t['columns'] as $c): ?><th><?= $h($c) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <?php foreach ($t['rows'] as $row): ?>
          <tr><?php foreach ($row as $cell): ?><td><?= $h($cell) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if (!$t['rows']): ?><tr><td colspan="<?= count($t['columns']) ?>">Nothing in this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
  <?php endforeach; ?>
  <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
