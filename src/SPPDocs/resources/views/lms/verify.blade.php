<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification - SPPDocs Academy</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 3rem 1rem;
            background: #0f172a;
            color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .verify-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            width: 100%;
            max-width: 650px;
            padding: 2.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }

        .verify-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .verify-badge-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        .verify-title {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            color: #ffffff;
        }
        .verify-subtitle {
            font-size: 0.95rem;
            color: #94a3b8;
            margin: 0;
        }

        .verify-form {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }
        .verify-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #475569;
            background: #0f172a;
            color: #ffffff;
            font-size: 1rem;
            font-family: monospace;
            text-transform: uppercase;
        }
        .verify-btn {
            background: #ea580c;
            color: #ffffff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .verify-btn:hover { opacity: 0.9; }

        .result-box {
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        .result-valid {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .result-invalid {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            text-align: center;
        }

        .result-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 800;
            font-size: 1.15rem;
            color: #10b981;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid rgba(16, 185, 129, 0.2);
            padding-bottom: 0.75rem;
        }

        .data-grid {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 0.75rem;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .data-label {
            color: #94a3b8;
            font-weight: 600;
        }
        .data-val {
            color: #ffffff;
            font-weight: 600;
        }

        .view-cert-btn {
            display: inline-block;
            margin-top: 1.25rem;
            background: #10b981;
            color: #ffffff;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="verify-card">
        <div class="verify-header">
            <span class="verify-badge-icon">🛡️</span>
            <h1 class="verify-title">Credential Verification</h1>
            <p class="verify-subtitle">Validate the authenticity of SPPDocs Academy certificates of completion.</p>
        </div>

        <form action="@url('lms/verify')" method="GET" class="verify-form">
            <input type="text" 
                   name="code" 
                   value="{{ $code }}" 
                   placeholder="e.g. SPP-LMS-2026-A1B2C3D4" 
                   required 
                   class="verify-input">
            <button type="submit" class="verify-btn">Verify</button>
        </form>

        @if(!empty($certificate))
            <div class="result-box result-valid">
                <div class="result-status">
                    <span>✅</span>
                    <span>Verified Authentic Credential</span>
                </div>

                <div class="data-grid">
                    <span class="data-label">Student Name:</span>
                    <span class="data-val">{{ $certificate['student_name'] }}</span>

                    <span class="data-label">Curriculum:</span>
                    <span class="data-val" style="color: #ea580c;">{{ $certificate['course_title'] }}</span>

                    <span class="data-label">Issue Date:</span>
                    <span class="data-val">{{ $certificate['issue_date'] }}</span>

                    <span class="data-label">Instructor:</span>
                    <span class="data-val">{{ $certificate['instructor'] }}</span>

                    <span class="data-label">Certificate ID:</span>
                    <span class="data-val" style="font-family: monospace;">{{ $certificate['certificate_id'] }}</span>

                    <span class="data-label">Security Hash:</span>
                    <span class="data-val" style="font-family: monospace; font-size: 0.75rem; color: #94a3b8; word-break: break-all;">
                        {{ $certificate['verification_hash'] ?? 'Verified by SPPDocs Engine' }}
                    </span>
                </div>

                @if(!empty($certificate['project_id']))
                    <a href="@url('lms/certificate?projectId=' . $certificate['project_id'] . '&code=' . $certificate['certificate_id'])" 
                       target="_blank" 
                       class="view-cert-btn">
                        View Official Certificate &rarr;
                    </a>
                @endif
            </div>
        @elseif($code)
            <div class="result-box result-invalid">
                <span style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem;">❌</span>
                <div style="font-weight: 800; font-size: 1.1rem; color: #ef4444; margin-bottom: 0.35rem;">
                    Invalid or Unverified Credential
                </div>
                <p style="color: #94a3b8; font-size: 0.9rem; margin: 0;">
                    No verified certificate matches code <strong>{{ $code }}</strong>. Please check for typographical errors and try again.
                </p>
            </div>
        @endif
    </div>

</body>
</html>
