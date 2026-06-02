<?php
$statuses = [
    'Open',
    'In Progress',
    'In Review',
    'To be Tested',
    'Delayed',
    'Cancelled',
    'Closed',
    'On Hold',
    'Completed'
];
?>
<div class="kanban-board">
    <?php foreach ($statuses as $status): ?>
        <div class="kanban-column" data-status="<?= $status ?>">
            <h5><?= $status ?></h5>
            <div class="kanban-items" id="<?= strtolower(str_replace(' ', '-', $status)) ?>">
                <?php mysqli_data_seek($lines, 0);
                    while ($line = mysqli_fetch_assoc($lines)) {
                        if ($line['status'] === $status) { ?>
                        <div class="kanban-item" draggable="true" data-id="<?= $line['id'] ?>" data-status="<?= $line['status'] ?>">
                            <?= htmlspecialchars($line['product'] ?? $line['description']) ?>
                        </div>
                    <?php }
                    } ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    document.querySelectorAll('.kanban-item').forEach(item => {
        item.addEventListener('dragstart', e => {
            e.dataTransfer.setData('id', item.dataset.id);
        });
    });

    document.querySelectorAll('.kanban-items').forEach(container => {
        container.addEventListener('dragover', e => e.preventDefault());
        container.addEventListener('drop', e => {
            e.preventDefault();
            const id = e.dataTransfer.getData('id');
            const card = document.querySelector(`.kanban-item[data-id='${id}']`);
            container.appendChild(card);

            const newStatus = container.parentElement.getAttribute('data-status');

            fetch('handle-order-line.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ol_id=${id}&status=${encodeURIComponent(newStatus)}`
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error: ' + (data.error || 'Failed to update'));
                    }
                })
                .catch(() => alert('AJAX error'));
        });
    });
</script>