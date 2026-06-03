/**
 * Dashboard JS
 * WMS - Warehouse Management System
 * ============================================================================
 */

(function () {
    'use strict';

    // ==========================================================================
    // Initialize Date Range Pickers
    // ==========================================================================

    // Task Date Range Filter
    if (document.getElementById('taskDateRange')) {
        const taskDateRange = flatpickr("#taskDateRange", {
            mode: "range",
            dateFormat: "d M Y",
            defaultDate: [
                new Date(new Date().setDate(new Date().getDate() - 30)),
                new Date()
            ],
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    // Format dates as Y-m-d for backend
                    const startDate = selectedDates[0].toISOString().split('T')[0];
                    const endDate = selectedDates[1].toISOString().split('T')[0];

                    document.getElementById('taskStartDate').value = startDate;
                    document.getElementById('taskEndDate').value = endDate;
                } else if (selectedDates.length === 0) {
                    // Clear hidden inputs if date range is cleared
                    document.getElementById('taskStartDate').value = '';
                    document.getElementById('taskEndDate').value = '';
                }
            }
        });

        // Set initial values if they exist in the form
        const taskStartDateInput = document.getElementById('taskStartDate');
        const taskEndDateInput = document.getElementById('taskEndDate');
        if (taskStartDateInput && taskEndDateInput && taskStartDateInput.value && taskEndDateInput.value) {
            taskDateRange.setDate([
                new Date(taskStartDateInput.value),
                new Date(taskEndDateInput.value)
            ]);
        }
    }

    // Team Date Range Filter
    if (document.getElementById('teamDateRange')) {
        const teamDateRange = flatpickr("#teamDateRange", {
            mode: "range",
            dateFormat: "d M Y",
            defaultDate: [
                new Date(new Date().setDate(new Date().getDate() - 30)),
                new Date()
            ],
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    // Format dates as Y-m-d for backend
                    const startDate = selectedDates[0].toISOString().split('T')[0];
                    const endDate = selectedDates[1].toISOString().split('T')[0];

                    document.getElementById('teamStartDate').value = startDate;
                    document.getElementById('teamEndDate').value = endDate;
                } else if (selectedDates.length === 0) {
                    // Clear hidden inputs if date range is cleared
                    document.getElementById('teamStartDate').value = '';
                    document.getElementById('teamEndDate').value = '';
                }
            }
        });

        // Set initial values if they exist in the form
        const teamStartDateInput = document.getElementById('teamStartDate');
        const teamEndDateInput = document.getElementById('teamEndDate');
        if (teamStartDateInput && teamEndDateInput && teamStartDateInput.value && teamEndDateInput.value) {
            teamDateRange.setDate([
                new Date(teamStartDateInput.value),
                new Date(teamEndDateInput.value)
            ]);
        }
    }

    // ==========================================================================
    // Chart Configuration
    // ==========================================================================

    const chartColors = {
        primary: '#0077b6',
        primaryLight: '#00b4d8',
        accent: '#90e0ef',
        success: '#28c76f',
        warning: '#ff9f43',
        danger: '#ea5455',
        info: '#00cfe8',
        textMuted: '#6c757d',
        gridColor: '#f0f0f0'
    };

    // ==========================================================================
    // Task Trend Chart (Area Chart)
    // ==========================================================================

    const taskTrendChartEl = document.querySelector("#taskTrendChart");

    if (taskTrendChartEl) {
        // Get trend data from data attributes
        const trendDataAttr = taskTrendChartEl.getAttribute('data-trend-data');
        const trendCategoriesAttr = taskTrendChartEl.getAttribute('data-trend-categories');

        let trendData = {
            todo: [0, 0, 0, 0, 0, 0],
            request: [0, 0, 0, 0, 0, 0], // backward compatibility
            inprogress: [0, 0, 0, 0, 0, 0],
            completed: [0, 0, 0, 0, 0, 0]
        };
        let categories = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];

        if (trendDataAttr) {
            try {
                trendData = JSON.parse(trendDataAttr);
            } catch (e) {
                console.error('Error parsing trend data:', e);
            }
        }

        if (trendCategoriesAttr) {
            try {
                categories = JSON.parse(trendCategoriesAttr);
            } catch (e) {
                console.error('Error parsing trend categories:', e);
            }
        }

        // Use 'todo' if available, otherwise fallback to 'request' for backward compatibility
        const todoData = trendData.todo || trendData.request || [0, 0, 0, 0, 0, 0];

        const taskTrendOptions = {
            series: [{
                name: 'To Do',
                data: todoData
            }, {
                name: 'In Progress',
                data: trendData.inprogress || [0, 0, 0, 0, 0, 0]
            }, {
                name: 'Completed',
                data: trendData.completed || [0, 0, 0, 0, 0, 0]
            }],
            chart: {
                type: 'area',
                height: 300,
                toolbar: {
                    show: false
                },
                fontFamily: 'Public Sans, sans-serif'
            },
            colors: [chartColors.info, chartColors.primary, chartColors.success],
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.1,
                    stops: [0, 90, 100]
                }
            },
            xaxis: {
                categories: categories,
                labels: {
                    style: {
                        colors: chartColors.textMuted,
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: chartColors.textMuted,
                        fontSize: '12px'
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right'
            },
            tooltip: {
                shared: true,
                intersect: false
            },
            grid: {
                borderColor: chartColors.gridColor,
                strokeDashArray: 4
            }
        };

        const taskTrendChart = new ApexCharts(taskTrendChartEl, taskTrendOptions);
        taskTrendChart.render();
    }

    // ==========================================================================
    // Task Status Chart (Donut Chart)
    // ==========================================================================

    const taskStatusChartEl = document.querySelector("#taskStatusChart");

    if (taskStatusChartEl) {
        // Get status data from data attribute
        const statusDataAttr = taskStatusChartEl.getAttribute('data-status-data');

        let statusData = {};

        if (statusDataAttr) {
            try {
                statusData = JSON.parse(statusDataAttr);
            } catch (e) {
                console.error('Error parsing status data:', e);
            }
        }

        // Define status order and labels
        const statusOrder = ['todo', 'hold', 'issue', 'inprogress', 'review', 'done'];
        const statusLabels = {
            todo: 'To Do',
            request: 'To Do', // backward compatibility
            hold: 'Hold',
            issue: 'Issue',
            inprogress: 'In Progress',
            review: 'Review',
            done: 'Done',
            completed: 'Done' // backward compatibility
        };
        const statusColors = {
            todo: chartColors.info,
            request: chartColors.info, // backward compatibility
            hold: chartColors.warning,
            issue: chartColors.danger,
            inprogress: chartColors.primary,
            review: '#a8aaae', // secondary/gray
            done: chartColors.success,
            completed: chartColors.success // backward compatibility
        };

        // Build series and labels arrays in order
        const series = [];
        const labels = [];
        const colors = [];

        statusOrder.forEach(code => {
            // Support backward compatibility for 'request' -> 'todo'
            const value = statusData[code] || (code === 'todo' ? statusData.request : 0) || 0;
            if (value > 0 || code === 'todo' || code === 'hold' || code === 'issue' || code === 'inprogress' || code === 'review' || code === 'done') {
                series.push(value);
                labels.push(statusLabels[code] || code);
                colors.push(statusColors[code] || chartColors.textMuted);
            }
        });

        const taskStatusOptions = {
            series: series,
            chart: {
                type: 'donut',
                height: 250
            },
            labels: labels,
            colors: colors,
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total Tasks',
                                fontSize: '14px',
                                color: chartColors.textMuted,
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            },
            legend: {
                position: 'bottom',
                fontSize: '12px'
            },
            dataLabels: {
                enabled: false
            }
        };

        const taskStatusChart = new ApexCharts(taskStatusChartEl, taskStatusOptions);
        taskStatusChart.render();
    }

    // ==========================================================================
    // Team Performance Chart (Stacked Bar Chart)
    // ==========================================================================

    const teamPerformanceChartEl = document.querySelector("#teamPerformanceChart");

    if (teamPerformanceChartEl) {
        // Get team members data from data attribute
        const teamMembersData = teamPerformanceChartEl.getAttribute('data-team-members');
        let teamMembers = [];

        if (teamMembersData) {
            try {
                teamMembers = JSON.parse(teamMembersData);
            } catch (e) {
                console.error('Error parsing team members data:', e);
            }
        }

        // Prepare data for chart
        const teamNames = teamMembers.map(member => member.name);
        const todoData = teamMembers.map(member => (member.todo || member.request || 0));
        const holdData = teamMembers.map(member => member.hold || 0);
        const issueData = teamMembers.map(member => member.issue || 0);
        const progressData = teamMembers.map(member => member.progress || 0);
        const reviewData = teamMembers.map(member => member.review || 0);
        const completedData = teamMembers.map(member => member.completed || 0);

        const teamPerformanceOptions = {
            series: [{
                name: 'To Do',
                data: todoData
            }, {
                name: 'Hold',
                data: holdData
            }, {
                name: 'Issue',
                data: issueData
            }, {
                name: 'In Progress',
                data: progressData
            }, {
                name: 'Review',
                data: reviewData
            }, {
                name: 'Completed',
                data: completedData
            }],
            chart: {
                type: 'bar',
                height: 350,
                stacked: true,
                toolbar: {
                    show: false
                },
                fontFamily: 'Public Sans, sans-serif'
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '50%',
                    borderRadius: 8,
                    borderRadiusApplication: 'end',
                    borderRadiusWhenStacked: 'last'
                },
            },
            colors: [chartColors.info, chartColors.warning, chartColors.danger, chartColors.primary, '#a8aaae', chartColors.success],
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: teamNames.length > 0 ? teamNames : ['No Data'],
                labels: {
                    style: {
                        colors: chartColors.textMuted,
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: chartColors.textMuted,
                        fontSize: '12px'
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right'
            },
            fill: {
                opacity: 1
            },
            grid: {
                borderColor: chartColors.gridColor,
                strokeDashArray: 4
            }
        };

        const teamPerformanceChart = new ApexCharts(teamPerformanceChartEl, teamPerformanceOptions);
        teamPerformanceChart.render();
    }

})();

