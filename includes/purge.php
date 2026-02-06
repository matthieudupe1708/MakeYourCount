<?php
// includes/purge.php

function myc_cutoff_date(): string {
    // “> 6 mois” au jour près
    return (new DateTime('today'))->modify('-6 months')->format('Y-m-d');
}

function myc_purge_group_if_needed(PDO $pdo, int $groupId): void {
    $cutoff = myc_cutoff_date();

    // Déjà snapshoté/purgé pour ce cutoff ? => on ne refait rien
    $stmt = $pdo->prepare("SELECT id FROM group_snapshots WHERE group_id=? AND cutoff_date=?");
    $stmt->execute([$groupId, $cutoff]);
    $existingId = $stmt->fetchColumn();
    if ($existingId) return;

    // On fait une purge “transactionnelle”
    $pdo->beginTransaction();
    try {
        // Crée snapshot
        $stmt = $pdo->prepare("INSERT INTO group_snapshots (group_id, cutoff_date) VALUES (?,?)");
        $stmt->execute([$groupId, $cutoff]);
        $snapshotId = (int)$pdo->lastInsertId();

        // Total groupe jusqu’au cutoff
        $stmt = $pdo->prepare("
          SELECT COALESCE(SUM(e.amount),0)
          FROM expenses e
          WHERE e.group_id=?
            AND COALESCE(e.expense_date, DATE(e.created_at)) <= ?
        ");
        $stmt->execute([$groupId, $cutoff]);
        $groupTotal = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO group_snapshot_group (snapshot_id, group_total) VALUES (?,?)");
        $stmt->execute([$snapshotId, $groupTotal]);

        // payé par user jusqu’au cutoff
        $stmt = $pdo->prepare("
          SELECT e.payer_id AS user_id, COALESCE(SUM(e.amount),0) AS paid
          FROM expenses e
          WHERE e.group_id=?
            AND COALESCE(e.expense_date, DATE(e.created_at)) <= ?
          GROUP BY e.payer_id
        ");
        $stmt->execute([$groupId, $cutoff]);
        $paid = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $paid[(int)$r['user_id']] = (float)$r['paid'];
        }

        // dû (= conso/parts) par user jusqu’au cutoff
        $stmt = $pdo->prepare("
          SELECT es.user_id, COALESCE(SUM(es.share_amount),0) AS owed
          FROM expense_shares es
          JOIN expenses e ON e.id = es.expense_id
          WHERE e.group_id=?
            AND COALESCE(e.expense_date, DATE(e.created_at)) <= ?
          GROUP BY es.user_id
        ");
        $stmt->execute([$groupId, $cutoff]);
        $owed = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $owed[(int)$r['user_id']] = (float)$r['owed'];
        }

        // membres du groupe
        $stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id=?");
        $stmt->execute([$groupId]);
        $members = array_map(fn($x)=>(int)$x['user_id'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        $ins = $pdo->prepare("
          INSERT INTO group_snapshot_user (snapshot_id, user_id, balance, personal_total, paid_total)
          VALUES (?,?,?,?,?)
        ");

        foreach ($members as $uid) {
            $p = (float)($paid[$uid] ?? 0);
            $o = (float)($owed[$uid] ?? 0);
            $bal = round($p - $o, 2);
            $ins->execute([$snapshotId, $uid, $bal, round($o,2), round($p,2)]);
        }

        // Suppression anciennes lignes (shares puis expenses)
        $stmt = $pdo->prepare("
          DELETE es FROM expense_shares es
          JOIN expenses e ON e.id = es.expense_id
          WHERE e.group_id=?
            AND COALESCE(e.expense_date, DATE(e.created_at)) <= ?
        ");
        $stmt->execute([$groupId, $cutoff]);

        $stmt = $pdo->prepare("
          DELETE FROM expenses
          WHERE group_id=?
            AND COALESCE(expense_date, DATE(created_at)) <= ?
        ");
        $stmt->execute([$groupId, $cutoff]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        // En prod tu peux logger. Ici on reste silencieux pour ne pas casser l’UX.
    }
}

function myc_get_latest_snapshot_id(PDO $pdo, int $groupId): ?int {
    $stmt = $pdo->prepare("
      SELECT id FROM group_snapshots
      WHERE group_id=?
      ORDER BY cutoff_date DESC
      LIMIT 1
    ");
    $stmt->execute([$groupId]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}
