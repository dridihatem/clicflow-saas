import Chart from 'chart.js/auto';

const initDashboardCharts = () => {
    const root = document.getElementById('flowdesk-dashboard-charts-root');
    if (!root) {
        return;
    }

    let payload = {};
    try {
        payload = JSON.parse(root.dataset.chart || '{}');
    } catch {
        payload = {};
    }

    const labelsRaw = payload.labels ?? [];
    const labels = labelsRaw.map((s) => {
        // expects YYYY-MM
        if (typeof s !== 'string' || !/^\d{4}-\d{2}$/.test(s)) return String(s ?? '');
        const [y, m] = s.split('-').map((x) => Number(x));
        const d = new Date(y, (m ?? 1) - 1, 1);
        return d.toLocaleDateString(undefined, { month: 'short', year: '2-digit' });
    });
    const n = labels.length;
    const pad = (arr) => {
        const a = Array.isArray(arr) ? arr.map((v) => Number(v) || 0) : [];
        if (n <= 0) return a;
        while (a.length < n) a.push(0);
        return a.slice(0, n);
    };
    const counts = pad(payload.counts ?? []);
    const paidMinor = pad(payload.paidMinor ?? []);
    const companiesCreated = pad(payload.companiesCreated ?? []);
    const byChannel = payload.paymentsByChannel ?? { labels: [], amounts_minor: [] };

    const minorScale = Number(payload.minorScale) > 0 ? Number(payload.minorScale) : 100;
    const minorToMajor = (v) => Number(v) / minorScale;

    const isDark = document.documentElement.classList.contains('dark');
    const grid = isDark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(15, 23, 42, 0.06)';
    const tick = isDark ? '#94a3b8' : '#64748b';

    const ctx1 = document.getElementById('chart-dashboard-invoices');
    if (ctx1) {
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: root.dataset.labelInvoices ?? 'Payments',
                        data: counts,
                        borderRadius: 8,
                        backgroundColor: 'rgba(234, 88, 12, 0.55)',
                        borderColor: 'rgba(234, 88, 12, 1)',
                        borderWidth: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: tick } } },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: tick } },
                    y: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick, precision: 0 } },
                },
            },
        });
    }

    const ctx2 = document.getElementById('chart-dashboard-revenue');
    if (ctx2) {
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: root.dataset.labelPaid ?? 'Payment volume',
                        data: paidMinor.map(minorToMajor),
                        fill: true,
                        tension: 0.35,
                        borderColor: 'rgb(234, 88, 12)',
                        backgroundColor: 'rgba(234, 88, 12, 0.14)',
                        pointRadius: 4,
                        pointBackgroundColor: 'rgb(194, 65, 12)',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: tick } } },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: tick } },
                    y: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick } },
                },
            },
        });
    }

    const palette = [
        'rgba(234, 88, 12, 0.85)',
        'rgba(249, 115, 22, 0.80)',
        'rgba(251, 191, 36, 0.85)',
        'rgba(194, 65, 12, 0.80)',
        'rgba(154, 52, 18, 0.75)',
        'rgba(251, 146, 60, 0.80)',
        'rgba(120, 53, 15, 0.70)',
    ];

    const ctxChannel = document.getElementById('chart-dashboard-payments-channel');
    if (ctxChannel && Array.isArray(byChannel.labels) && byChannel.labels.length > 0) {
        const chLabels = byChannel.labels;
        const chData = (byChannel.amounts_minor ?? []).map(minorToMajor);
        new Chart(ctxChannel, {
            type: 'doughnut',
            data: {
                labels: chLabels,
                datasets: [
                    {
                        data: chData,
                        backgroundColor: chLabels.map((_, i) => palette[i % palette.length]),
                        borderColor: isDark ? 'rgba(15, 23, 42, 0.9)' : '#fff',
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: tick } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const v = ctx.raw ?? 0;
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0) || 1;
                                const pct = Math.round((v / total) * 1000) / 10;
                                return `${ctx.label}: ${v} (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    const ctx3 = document.getElementById('chart-admin-companies');
    if (ctx3) {
        new Chart(ctx3, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: root.dataset.labelCompanies ?? 'Companies created',
                        data: companiesCreated,
                        borderRadius: 8,
                        backgroundColor: 'rgba(249, 115, 22, 0.55)',
                        borderColor: 'rgba(234, 88, 12, 1)',
                        borderWidth: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: tick } } },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: tick } },
                    y: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick, precision: 0 } },
                },
            },
        });
    }
};

const initUsageOverviewChart = () => {
    const usageRoot = document.getElementById('flowdesk-usage-overview-chart-root');
    const ctxUsage = document.getElementById('chart-dashboard-usage');
    if (!usageRoot || !ctxUsage) {
        return;
    }

    let usagePayload = {};
    try {
        usagePayload = JSON.parse(usageRoot.dataset.usageChart || '{}');
    } catch {
        usagePayload = {};
    }

    const usageLabels = Array.isArray(usagePayload.labels) ? usagePayload.labels : [];
    const usageValues = Array.isArray(usagePayload.values)
        ? usagePayload.values.map((v) => Number(v) || 0)
        : [];

    if (usageLabels.length === 0) {
        return;
    }

    const isDark = document.documentElement.classList.contains('dark');
    const grid = isDark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(15, 23, 42, 0.06)';
    const tick = isDark ? '#94a3b8' : '#64748b';

    new Chart(ctxUsage, {
        type: 'bar',
        data: {
            labels: usageLabels,
            datasets: [
                {
                    label: usageRoot.dataset.labelUsage ?? 'Usage',
                    data: usageValues,
                    borderRadius: 8,
                    backgroundColor: [
                        'rgba(6, 182, 212, 0.55)',
                        'rgba(99, 102, 241, 0.55)',
                        'rgba(16, 185, 129, 0.55)',
                    ],
                    borderColor: [
                        'rgba(6, 182, 212, 1)',
                        'rgba(99, 102, 241, 1)',
                        'rgba(16, 185, 129, 1)',
                    ],
                    borderWidth: 1,
                },
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick, precision: 0 } },
                y: { grid: { display: false }, ticks: { color: tick } },
            },
        },
    });
};

const initAllDashboardCharts = () => {
    initDashboardCharts();
    initUsageOverviewChart();
};

// Vite module scripts can execute after DOMContentLoaded on fast navigations/cached loads.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllDashboardCharts, { once: true });
} else {
    initAllDashboardCharts();
}
