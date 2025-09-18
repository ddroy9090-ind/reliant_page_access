'use strict';

(function initDashboardCharts() {
  if (typeof Chart === 'undefined') {
    return;
  }

  const monthlyCanvas = document.getElementById('monthlyOverview');
  if (monthlyCanvas) {
    const ctx = monthlyCanvas.getContext('2d');
    const existingChart = typeof Chart.getChart === 'function'
      ? Chart.getChart(monthlyCanvas)
      : null;
    if (existingChart) {
      existingChart.destroy();
    }

    let labels = [];
    let values = [];
    const rawDataset = monthlyCanvas.dataset.chart;

    if (rawDataset) {
      try {
        const parsed = JSON.parse(rawDataset);
        if (Array.isArray(parsed.labels) && parsed.labels.length > 0) {
          labels = parsed.labels;
          const countArray = Array.isArray(parsed.counts) ? parsed.counts : [];
          values = labels.map((_, index) => {
            const value = Number(countArray[index]);
            return Number.isFinite(value) ? value : 0;
          });
        }
      } catch (error) {
        console.warn('Unable to parse monthly chart data', error);
      }
    }

    if (!labels.length) {
      labels = ['No data'];
      values = [0];
    }

    const maxValue = values.reduce((acc, value) => Math.max(acc, value), 0);
    const yAxisOptions = {
      beginAtZero: true,
      ticks: {
        callback: value => `${value}`
      }
    };

    if (maxValue === 0) {
      yAxisOptions.suggestedMax = 5;
    }

    new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Visits per month',
            data: values,
            borderColor: '#d01f28',
            backgroundColor: 'rgba(208, 31, 40, 0.15)',
            tension: 0.35,
            pointBackgroundColor: '#d01f28',
            pointBorderColor: '#d01f28',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#d01f28',
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: yAxisOptions
        }
      }
    });
  }

  const deviceCanvas = document.getElementById('deviceUsage');
  if (deviceCanvas) {
    const ctx = deviceCanvas.getContext('2d');
    const existingChart = typeof Chart.getChart === 'function'
      ? Chart.getChart(deviceCanvas)
      : null;
    if (existingChart) {
      existingChart.destroy();
    }

    new Chart(ctx, {
      type: 'pie',
      data: {
        labels: ['Desktop 65%', 'Mobile 30%', 'Tablet 5%'],
        datasets: [
          {
            data: [65, 30, 5],
            backgroundColor: ['#e63946', '#d01f28', '#ff7b7b'],
            borderWidth: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              usePointStyle: true
            }
          }
        }
      }
    });
  }
})();
