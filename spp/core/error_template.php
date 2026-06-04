<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exception: <?php echo $title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { 
            --bg: #09090b; 
            --surface: rgba(24, 24, 27, 0.7); 
            --primary: #f43f5e; 
            --text: #f4f4f5; 
            --muted: #a1a1aa; 
            --border: rgba(255,255,255,0.08);
            --glow: rgba(244, 63, 94, 0.15);
        }
        body { 
            margin: 0; 
            background: var(--bg); 
            background-image: radial-gradient(circle at top right, var(--glow), transparent 400px);
            color: var(--text); 
            font-family: 'Inter', sans-serif; 
            line-height: 1.6; 
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 60px auto; padding: 0 20px; animation: fadeUp 0.6s ease-out forwards; opacity: 0; transform: translateY(20px); }
        .header { 
            background: var(--surface); 
            backdrop-filter: blur(12px);
            padding: 40px; 
            border-radius: 16px; 
            border: 1px solid var(--border);
            border-left: 6px solid var(--primary); 
            margin-bottom: 40px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); 
        }
        .type { font-size: 13px; text-transform: uppercase; letter-spacing: 2px; color: var(--primary); font-weight: 700; margin-bottom: 15px; }
        .message { font-size: 28px; font-weight: 700; margin: 0; letter-spacing: -0.5px; line-height: 1.3; }
        .location { margin-top: 20px; color: var(--muted); font-family: 'Fira Code', monospace; font-size: 14px; background: rgba(0,0,0,0.3); padding: 8px 12px; border-radius: 6px; display: inline-block; }
        .trace-container { 
            background: var(--surface); 
            backdrop-filter: blur(12px);
            padding: 40px; 
            border-radius: 16px; 
            border: 1px solid var(--border);
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.3); 
        }
        .trace-title { font-size: 20px; font-weight: 600; margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        .trace-item { margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px dashed var(--border); transition: all 0.2s ease; }
        .trace-item:hover { transform: translateX(5px); }
        .trace-item:last-child { border: none; margin-bottom: 0; padding-bottom: 0; }
        .trace-call { font-family: 'Fira Code', monospace; color: #38bdf8; font-weight: 500; font-size: 15px; }
        .trace-loc { font-size: 13px; color: var(--muted); margin-top: 8px; }
        .code-snippet { 
            margin-top: 20px; 
            background: #000000; 
            border-radius: 10px; 
            padding: 20px 0; 
            overflow-x: auto; 
            font-family: 'Fira Code', monospace; 
            font-size: 14px; 
            border: 1px solid var(--border); 
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
        }
        .code-line { padding: 4px 20px; white-space: pre; color: #e2e8f0; transition: background 0.2s; }
        .code-line:hover { background: rgba(255,255,255,0.05); }
        .error-line { background: rgba(244, 63, 94, 0.15); border-left: 4px solid var(--primary); padding-left: 16px; color: #fff; text-shadow: 0 0 10px rgba(244,63,94,0.4); }
        .line-num { color: #475569; display: inline-block; width: 45px; user-select: none; border-right: 1px solid #333; margin-right: 15px; }
        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="type"><?php echo $title; ?></div>
            <h1 class="message"><?php echo $message; ?></h1>
            <div class="location">in <?php echo $file; ?> on line <?php echo $line; ?></div>
        </div>

        <div class="trace-container">
            <div class="trace-title">Stack Trace & Code Snippets</div>

            <!-- Main Exception Snippet -->
            <div class="trace-item snippet-active">
                <div class="trace-call">
                    <strong>Exception Thrown Here:</strong>
                </div>
                <div class="trace-loc"><?php echo $file; ?>:<?php echo $line; ?></div>
                <div class="code-snippet">
                    <?php 
                    if (file_exists($file)) {
                        $lines = file($file);
                        $start = max(0, $line - 6);
                        $end = min(count($lines), $line + 5);
                        for ($j = $start; $j < $end; $j++) {
                            $isErrorLine = ($j + 1) === $line;
                            $lineStr = htmlspecialchars($lines[$j]);
                            if ($isErrorLine) {
                                echo "<div class='code-line error-line'><span class='line-num'>" . ($j + 1) . "</span> $lineStr</div>";
                            } else {
                                echo "<div class='code-line'><span class='line-num'>" . ($j + 1) . "</span> $lineStr</div>";
                            }
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- Stack Trace -->
            <?php foreach ($trace as $i => $t): ?>
                <div class="trace-item">
                    <div class="trace-call">
                        #<?php echo $i; ?> 
                        <?php echo isset($t['class']) ? $t['class'] . $t['type'] : ''; ?>
                        <?php echo $t['function']; ?>(...)
                    </div>
                    <?php if (isset($t['file'])): ?>
                        <div class="trace-loc"><?php echo $t['file']; ?>:<?php echo $t['line']; ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
