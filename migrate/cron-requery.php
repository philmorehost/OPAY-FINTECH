<?php
/**
 * Automated Transaction Requery Cron
 * Rechecks pending/failed transactions from the last 5 minutes.
 * Can be triggered via real Cron or AJAX Hook.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// Only allow execution once every minute (Robust File-based lock)
$lockFile = sys_get_temp_dir() . '/billpay_requery.lock';
if (file_exists($lockFile) && time() - filemtime($lockFile) < 55 && !isset($_GET['force'])) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Rate limited. Try again in a minute.']);
        exit;
    }
    return;
}
touch($lockFile);

// Fetch pending or failed transactions from last 5 minutes, queried < 5 times
$stmt = $pdo->prepare("SELECT id FROM transactions WHERE (status = 'pending' OR status = 'failed') AND date > DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND query_count < 5 ORDER BY date DESC LIMIT 20");
$stmt->execute();
$toQuery = $stmt->fetchAll(PDO::FETCH_COLUMN);

$results = [];
foreach ($toQuery as $txId) {
    $results[$txId] = requeryTransaction($pdo, $txId);
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'queried' => count($toQuery), 'results' => $results]);
    exit;
}

echo "Requery completed for " . count($toQuery) . " transactions.";
