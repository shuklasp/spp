<?php
/**
 * Modern PDF Template
 * Designed for Dompdf / Puppeteer using modern CSS.
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report Export</title>
    <style>
        @page {
            margin: 40px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #0056b3;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
        }
        tr:nth-child(even) {
            background-color: #fcfcfc;
        }
        .footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= htmlspecialchars($org_name ?? 'SPP Report') ?></h1>
        <p>Generated on <?= date('Y-m-d H:i:s') ?></p>
    </div>

    <?php if (!empty($data)): ?>
        <table>
            <thead>
                <tr>
                    <?php foreach (array_keys($data[0]) as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($row as $val): ?>
                            <td><?= htmlspecialchars((string)$val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center; font-style: italic;">No data available.</p>
    <?php endif; ?>

    <div class="footer">
        Page <span class="page-number"></span> | <?= htmlspecialchars($org_name ?? '') ?>
    </div>
</body>
</html>
