{{--
  SPPReport V5 Dual-Mode Report Template
  Demonstrates unified screen-mode (interactive filters, scrollable tables) and print-mode (printer-friendly)
--}}
@extends('layouts.app')
@section('title', $report['name'] ?? 'Monthly Sales Report')
@section('content')
<style>
    /* ── Dual-Mode Display Logistics ── */
    @media screen {
        .spp-print-mode { display: none !important; }
        .spp-screen-mode { display: block; }
        .report-container { max-width: 1200px; margin: 2rem auto; padding: 2rem; background: #ffffff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .filter-panel { background: #f8fafc; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 2rem; }
        .scrollable-table { overflow-x: auto; max-height: 500px; border: 1px solid #e2e8f0; border-radius: 8px; }
        .scrollable-table table { width: 100%; border-collapse: collapse; }
        .scrollable-table th { position: sticky; top: 0; background: #f1f5f9; padding: 1rem; font-weight: 600; text-align: left; border-bottom: 2px solid #cbd5e1; }
        .scrollable-table td { padding: 1rem; border-bottom: 1px solid #e2e8f0; }
        .btn-action { background: #6366f1; color: #fff; padding: 0.6rem 1.2rem; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; }
        .btn-print { background: #10b981; color: #fff; padding: 0.6rem 1.2rem; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; margin-left: 1rem; }
    }
    @media print {
        .spp-screen-mode { display: none !important; }
        .spp-print-mode { display: block; }
        body { background: #fff; color: #000; font-family: serif; font-size: 12pt; margin: 0; padding: 0; }
        .print-header { border-bottom: 2px solid #000; padding-bottom: 1rem; margin-bottom: 2rem; }
        .print-table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        .print-table th, .print-table td { border: 1px solid #000; padding: 0.5rem; text-align: left; }
        .print-footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9pt; border-top: 1px solid #ccc; padding-top: 0.5rem; }
    }
</style>

<div class="report-container">
    {{-- ── SCREEN MODE SECTION ── --}}
    <div class="spp-screen-mode">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h1 style="margin: 0; color: #0f172a;">📊 {{ $report['name'] ?? 'Monthly Sales & Performance Summary' }}</h1>
            <div>
                <button class="btn-print" onclick="window.print()">🖨️ Print Report</button>
            </div>
        </div>
        
        {{-- Filter Panel --}}
        <div class="filter-panel">
            <form hx-get="@url('reports/monthly_sales')" hx-target="#report-table-wrapper" hx-indicator="#report-loading-indicator" style="display: flex; gap: 1.5rem; align-items: flex-end;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.4rem;">Start Date</label>
                    <input type="date" name="start_date" value="{{ $params['start_date'] ?? '2026-06-01' }}" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.4rem;">End Date</label>
                    <input type="date" name="end_date" value="{{ $params['end_date'] ?? '2026-06-30' }}" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                </div>
                <div>
                    <button type="submit" class="btn-action">🔍 Filter Data</button>
                </div>
            </form>
        </div>

        {{-- Interactive Scrollable Table Wrapper --}}
        <div id="report-table-wrapper">
            <div id="report-loading-indicator" class="htmx-indicator" style="text-align: center; padding: 1rem; color: #64748b;">🔄 Loading report data...</div>
            <div class="scrollable-table">
                <table>
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Total Sales</th>
                            <th>Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data as $row)
                            <tr>
                                <td>{{ $row->department }}</td>
                                <td>${{ number_format($row->total_sales, 2) }}</td>
                                <td>{{ number_format($row->transactions) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 2rem; color: #64748b;">No sales records found for the selected date range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Turbo Stream Live Update Target --}}
        <div id="report-monthly-sales-stream"></div>
    </div>

    {{-- ── PRINT MODE SECTION ── --}}
    <div class="spp-print-mode">
        <div class="print-header">
            <h1 style="margin: 0; font-size: 24pt;">{{ $report['name'] ?? 'Monthly Sales & Performance Summary' }}</h1>
            <p style="margin: 0.5rem 0 0 0; color: #333;">Generated on: {{ date('Y-m-d H:i:s') }} | Filter Range: {{ $params['start_date'] ?? 'N/A' }} to {{ $params['end_date'] ?? 'N/A' }}</p>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Total Sales</th>
                    <th>Transactions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($data as $row)
                    <tr>
                        <td>{{ $row->department }}</td>
                        <td>${{ number_format($row->total_sales, 2) }}</td>
                        <td>{{ number_format($row->transactions) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 1rem;">No sales records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="print-footer">
            <p>SPPReport V5 Dual-Mode Engine | Confidential Financial Summary</p>
        </div>
    </div>
</div>
@endsection