<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="content-type" content="text/plain; charset=UTF-8"/>
</head>
<body>
    <table border="1">
        <?php
        $generator = $data['generator'] ?? [];
        $first = true;
        foreach ($generator as $row) {
            if ($first) {
                echo '<tr>';
                foreach (array_keys($row) as $th) {
                    echo '<th style="background-color:#4CAF50;color:white;">' . htmlspecialchars($th) . '</th>';
                }
                echo '</tr>';
                $first = false;
            }
            echo '<tr>';
            foreach ($row as $val) {
                $sanitizedVal = preg_match('/^[=\+\-@]/', (string) $val) ? "'" . $val : $val;
                echo '<td>' . htmlspecialchars((string) $sanitizedVal) . '</td>';
            }
            echo '</tr>';
        }
        ?>
    </table>
</body>
</html>
