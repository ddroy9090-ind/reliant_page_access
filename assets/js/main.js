'use strict';

(function initDashboardCharts() {
  if (typeof Chart === 'undefined') {
    return;
  }

  const monthlyCanvas = document.getElementById('monthlyOverview');
  if (monthlyCanvas) {
    const ctx = monthlyCanvas.getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [
          {
            label: 'Monthly Data',
            data: [4000, 3000, 2000, 2800, 1900, 2400],
            borderColor: '#d01f28',
            backgroundColor: 'rgba(208, 31, 40, 0.15)',
            tension: 0.4,
            pointBackgroundColor: '#d01f28',
            pointBorderColor: '#d01f28',
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
          y: {
            beginAtZero: true,
            ticks: {
              callback: value => `${value}`
            }
          }
        }
      }
    });
  }

  const deviceCanvas = document.getElementById('deviceUsage');
  if (deviceCanvas) {
    const ctx = deviceCanvas.getContext('2d');
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
