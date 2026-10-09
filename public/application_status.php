<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HarvestHub — Registration Status</title>
  <link rel="stylesheet" href="assets/style.css?v=36">
</head>
<body class="account-page">
  <main class="wrap" style="max-width: 640px; padding-top: 48px;">
    <section class="panel">
      <h1>Registration request status</h1>
      <p id="application-status-message" aria-live="polite">Checking your request…</p>
      <p id="application-status-reason" hidden></p>
      <a href="login.php">Return to sign in</a>
    </section>
  </main>
  <script>
    const token = new URLSearchParams(window.location.search).get('token') || '';
    const message = document.getElementById('application-status-message');
    const reason = document.getElementById('application-status-reason');
    fetch(`api.php?action=application_status&token=${encodeURIComponent(token)}`)
      .then(response => response.json())
      .then(data => {
        if (!data.ok) throw new Error(data.error || 'Could not retrieve this request.');
        const labels = { Pending: 'Your registration is awaiting administrator review.', Approved: 'Your registration was approved. You can now sign in.', Rejected: 'Your registration was not approved.' };
        message.textContent = labels[data.status] || 'This request has been processed.';
        if (data.status === 'Rejected' && data.reason) {
          reason.textContent = `Reason: ${data.reason}`;
          reason.hidden = false;
        }
      })
      .catch(error => { message.textContent = error.message; });
  </script>
</body>
</html>
