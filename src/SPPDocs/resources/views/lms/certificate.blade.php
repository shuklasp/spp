<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - {{ $certificate['student_name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 2rem 1rem;
            background: #0f172a;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .cert-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            align-items: center;
        }

        .cert-btn {
            background: #ea580c;
            color: #ffffff;
            border: none;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 4px 10px rgba(234, 88, 12, 0.3);
            transition: opacity 0.2s;
        }
        .cert-btn:hover { opacity: 0.9; }

        .cert-btn-sec {
            background: #334155;
            color: #f8fafc;
            box-shadow: none;
        }

        /* Certificate Container (Landscape 4:3 / A4 ratio) */
        .cert-wrapper {
            background: #ffffff;
            width: 100%;
            max-width: 900px;
            padding: 3.5rem 4rem;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
            position: relative;
            border: 14px solid #1e293b;
            outline: 3px solid #d97706;
            outline-offset: -8px;
            text-align: center;
        }

        .cert-header {
            margin-bottom: 2rem;
        }
        .cert-issuer {
            font-size: 0.9rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #ea580c;
            margin-bottom: 0.75rem;
        }
        .cert-title {
            font-size: 2.8rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
            font-family: Georgia, "Times New Roman", serif;
        }

        .cert-subtitle {
            font-size: 1.1rem;
            color: #64748b;
            margin-top: 1.5rem;
            font-style: italic;
        }

        .cert-student {
            font-size: 2.6rem;
            font-weight: 800;
            color: #0f172a;
            margin: 1rem 0;
            border-bottom: 2px solid #e2e8f0;
            display: inline-block;
            padding: 0 2rem 0.5rem 2rem;
            font-family: Georgia, "Times New Roman", serif;
        }

        .cert-statement {
            font-size: 1.1rem;
            color: #475569;
            max-width: 650px;
            margin: 1rem auto 1.5rem auto;
            line-height: 1.6;
        }

        .cert-course {
            font-size: 1.7rem;
            font-weight: 800;
            color: #ea580c;
            margin: 0.5rem 0 2rem 0;
        }

        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .cert-sig-block {
            text-align: left;
        }
        .cert-sig-line {
            font-family: "Brush Script MT", "Caveat", cursive, Georgia, serif;
            font-size: 1.8rem;
            color: #1e293b;
            margin-bottom: 0.2rem;
        }
        .cert-sig-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: #0f172a;
        }
        .cert-sig-title {
            font-size: 0.8rem;
            color: #64748b;
        }

        /* Gold Seal */
        .cert-seal {
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, #fde68a, #d97706);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(217, 119, 6, 0.4);
            border: 3px dashed #b45309;
            color: #78350f;
            font-weight: 900;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: center;
            line-height: 1.2;
            padding: 0.5rem;
        }

        .cert-meta-block {
            text-align: right;
            font-size: 0.82rem;
            color: #64748b;
            line-height: 1.5;
        }
        .cert-meta-code {
            font-family: monospace;
            font-weight: 700;
            color: #0f172a;
            font-size: 0.9rem;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .cert-actions {
                display: none !important;
            }
            .cert-wrapper {
                box-shadow: none;
                border: 10px solid #1e293b;
                max-width: 100%;
                width: 100%;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Action Bar (Hidden on Print) -->
    <div class="cert-actions">
        <a href="@url('lms?projectId=' . $project_id)" class="cert-btn cert-btn-sec">
            &larr; Back to Academy
        </a>
        <button onclick="window.print()" class="cert-btn">
            🖨️ Print / Save as PDF
        </button>
        @if(!empty($linkedin_url))
            <a href="{{ $linkedin_url }}" target="_blank" rel="noopener noreferrer" class="cert-btn" style="background: #0a66c2; box-shadow: 0 4px 10px rgba(10, 102, 194, 0.3);">
                💼 Add to LinkedIn
            </a>
        @endif
        <a href="@url('lms/verify?code=' . $certificate['certificate_id'])" target="_blank" class="cert-btn cert-btn-sec">
            🔗 Verification Page ↗
        </a>
    </div>

    <!-- Official Certificate Frame -->
    <div class="cert-wrapper">
        <div class="cert-header">
            <div class="cert-issuer">
                {{ $project['title'] ?? 'SPP' }} &bull; Academy of Enterprise Architecture
            </div>
            <h1 class="cert-title">Certificate of Completion</h1>
            <div class="cert-subtitle">This is to certify that</div>
        </div>

        <div class="cert-student">
            {{ $certificate['student_name'] }}
        </div>

        <div class="cert-statement">
            has successfully completed all required modules, lessons, and interactive assessments for the curriculum
        </div>

        <div class="cert-course">
            {{ $certificate['course_title'] }}
        </div>

        <div class="cert-footer">
            <div class="cert-sig-block">
                <div class="cert-sig-line">
                    {{ $certificate['instructor'] }}
                </div>
                <div class="cert-sig-name">
                    {{ $certificate['instructor'] }}
                </div>
                <div class="cert-sig-title">
                    {{ $certificate['instructor_title'] ?? 'Instructor' }}
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div class="cert-seal">
                    VERIFIED<br>★<br>OFFICIAL
                </div>

                @if(!empty($qr_code_svg))
                    <div class="cert-qr-block" style="text-align: center;">
                        <div style="background: #ffffff; padding: 4px; border: 1px solid #e2e8f0; border-radius: 8px; display: inline-block; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                            {!! $qr_code_svg !!}
                        </div>
                        <div style="font-size: 0.65rem; font-weight: 700; color: #64748b; margin-top: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            Scan to Verify
                        </div>
                    </div>
                @endif
            </div>

            <div class="cert-meta-block">
                <div>Issued: <strong>{{ $certificate['issue_date'] }}</strong></div>
                <div>Certificate ID:</div>
                <div class="cert-meta-code">{{ $certificate['certificate_id'] }}</div>
                <div style="font-size: 0.72rem; margin-top: 0.2rem; color: #94a3b8;">
                    Verify authenticity at @url('lms/verify')
                </div>
            </div>
        </div>
    </div>

</body>
</html>
