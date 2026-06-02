/**
 * Dashboard analytics chart — init on page load + dark mode sync.
 */
(function () {
  const canvas = document.getElementById('revenueChart');
  if (!canvas) return;

  let chart = null;

  function isDark() {
    return document.documentElement.getAttribute('data-theme') === 'dark';
  }

  function themeColors() {
    const dark = isDark();
    return {
      grid: dark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)',
      tick: dark ? 'rgba(255,255,255,.45)' : '#64748b',
      tipBg: dark ? '#1e293b' : '#fff',
      tipColor: dark ? '#f1f5f9' : '#0f172a',
      revenue: dark ? 'rgba(99,102,241,.7)' : 'rgba(79,70,229,.78)',
      cost: dark ? 'rgba(239,68,68,.5)' : 'rgba(239,68,68,.68)',
      profit: dark ? 'rgba(16,185,129,.55)' : 'rgba(16,185,129,.72)',
    };
  }

  function applyTheme() {
    if (!chart) return;
    const c = themeColors();
    chart.data.datasets[0].backgroundColor = c.revenue;
    chart.data.datasets[1].backgroundColor = c.cost;
    chart.data.datasets[2].backgroundColor = c.profit;
    chart.options.plugins.legend.labels.color = c.tick;
    chart.options.plugins.tooltip.backgroundColor = c.tipBg;
    chart.options.plugins.tooltip.titleColor = c.tipColor;
    chart.options.plugins.tooltip.bodyColor = c.tipColor;
    chart.options.scales.y.grid.color = c.grid;
    chart.options.scales.y.ticks.color = c.tick;
    chart.options.scales.x.ticks.color = c.tick;
    chart.update('none');
  }

  function buildOptions(xTicks) {
    const c = themeColors();
    const ticks = xTicks || { maxRotation: 0, maxTicksLimit: 12 };
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            color: c.tick,
            font: { size: 11 },
            boxWidth: 10,
            padding: 14,
            usePointStyle: true,
            pointStyle: 'rectRounded',
          },
        },
        tooltip: {
          backgroundColor: c.tipBg,
          titleColor: c.tipColor,
          bodyColor: c.tipColor,
          titleFont: { size: 11 },
          bodyFont: { size: 11 },
          padding: 10,
          cornerRadius: 8,
          callbacks: {
            label: (ctx) => ' $' + ctx.parsed.y.toLocaleString(),
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: c.grid },
          ticks: {
            color: c.tick,
            font: { size: 10 },
            maxTicksLimit: 5,
            callback: (v) => '$' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v),
          },
          border: { display: false },
        },
        x: {
          grid: { display: false },
          ticks: {
            color: c.tick,
            font: { size: 10 },
            maxRotation: ticks.maxRotation ?? 0,
            autoSkip: true,
            maxTicksLimit: ticks.maxTicksLimit ?? 12,
          },
          border: { display: false },
        },
      },
    };
  }

  function initChart() {
    let payload;
    try {
      payload = JSON.parse(canvas.dataset.initialChart || '{}');
    } catch {
      payload = {};
    }
    if (!payload.labels) return;

    const c = themeColors();
    chart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: payload.labels,
        datasets: [
          {
            label: 'Revenue',
            data: payload.revenue,
            backgroundColor: c.revenue,
            borderRadius: 5,
            borderSkipped: false,
          },
          {
            label: 'Cost',
            data: payload.cost,
            backgroundColor: c.cost,
            borderRadius: 5,
            borderSkipped: false,
          },
          {
            label: 'Profit',
            data: payload.profit,
            backgroundColor: c.profit,
            borderRadius: 5,
            borderSkipped: false,
          },
        ],
      },
      options: buildOptions(payload.x_ticks),
    });

    new MutationObserver(applyTheme).observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['data-theme'],
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChart);
  } else {
    initChart();
  }
})();
