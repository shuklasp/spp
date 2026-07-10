<h1>Report Export</h1>
<table border="1" cellpadding="4">
    <thead>
        <tr>
            <?php
            $rows = $data['data'] ?? [];
            if (!empty($rows)):
                foreach (array_keys($rows[0]) as $th): ?>
                    <th style="background-color:#eee;"><b><?= htmlspecialchars($th) ?></b></th>
                <?php endforeach;
            endif;
            ?>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($rows)):
            foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row as $val): ?>
                        <td><?= htmlspecialchars((string) $val) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach;
        endif; ?>
    </tbody>
</table>
