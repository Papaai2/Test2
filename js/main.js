document.addEventListener('DOMContentLoaded', function() {
    // --- Sidebar Toggle Functionality ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarWrapper = document.getElementById('sidebar-wrapper');

    if (sidebarToggle && sidebarWrapper) {
        sidebarToggle.addEventListener('click', function() {
            sidebarWrapper.classList.toggle('show');
        });
    }

    // --- Notification Dropdown Fetch and Mark as Read Functionality ---
    const notificationBell = document.getElementById('notification-bell');
    const notificationList = document.getElementById('notification-list');
    const notificationCount = document.getElementById('notification-count');

    if (notificationBell && notificationList && notificationCount) {
        // Function to fetch notifications
        const fetchNotifications = async () => {
            try {
                const response = await fetch(BASE_URL + '/api/get_notifications.php');
                const data = await response.json();

                if (data.success) {
                    notificationList.innerHTML = ''; // Clear previous notifications
                    if (data.notifications.length > 0) {
                        data.notifications.forEach(notification => {
                            const notificationLink = notification.request_id
                                ? `${BASE_URL}/requests/view.php?id=${notification.request_id}`
                                : `${BASE_URL}/notifications.php`;

                            const notificationItem = document.createElement('li');
                            notificationItem.innerHTML = `
                                <a class="dropdown-item notification-item ${notification.is_read == 0 ? 'unread-notification' : 'read-notification'}"
                                   href="${notificationLink}"
                                   data-notification-id="${notification.id}"
                                   data-request-id="${notification.request_id || ''}">
                                    <span class="notification-message">${notification.message}</span>
                                    <small class="notification-time">${new Date(notification.created_at + ' UTC').toLocaleString()}</small>
                                </a>
                            `;
                            notificationList.appendChild(notificationItem);
                        });

                        // Add click listener for dropdown notifications
                        notificationList.querySelectorAll('.notification-item').forEach(item => {
                            item.addEventListener('click', function(event) {
                                const targetLink = this.href;
                                window.location.href = targetLink;
                            });

                            item.addEventListener('mouseover', async function() {
                                const notificationId = this.dataset.notificationId;
                                if (notificationId && this.classList.contains('unread-notification')) {
                                    try {
                                        const response = await fetch(`${BASE_URL}/api/mark_notifications_read.php`, {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ notification_ids: [parseInt(notificationId)] })
                                        });
                                        const data = await response.json();

                                        if (data.success) {
                                            this.classList.remove('unread-notification');
                                            this.classList.add('read-notification');
                                            const currentCount = parseInt(notificationCount.textContent) || 0;
                                            if (currentCount > 0) {
                                                notificationCount.textContent = currentCount - 1;
                                            }
                                            if (currentCount - 1 === 0) {
                                                notificationCount.style.display = 'none';
                                            }
                                        }
                                    } catch (error) {
                                        console.error('Error marking notification as read on hover:', error);
                                    }
                                }
                            });
                        });

                        const unreadCount = data.notifications.filter(n => n.is_read == 0).length;
                        if (unreadCount > 0) {
                            notificationCount.textContent = unreadCount;
                            notificationCount.style.display = 'block';
                        } else {
                            notificationCount.style.display = 'none';
                        }
                    } else {
                        notificationList.innerHTML = '<li><span class="dropdown-item text-muted">No new notifications.</span></li>';
                        notificationCount.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error("Error fetching notifications: " + error);
                notificationList.innerHTML = '<li><span class="dropdown-item text-danger">Failed to load notifications.</span></li>';
            }
        };

        notificationBell.addEventListener('click', fetchNotifications);
        fetchNotifications(); // Initial fetch on page load
    }

    // --- Dashboard Enhancements ---

    const updateDashboardStats = async () => {
        const dashboardStatsDiv = document.getElementById('dashboard-stats');
        if (!dashboardStatsDiv) return;

        try {
            const response = await fetch(BASE_URL + '/api/get_dashboard_stats.php');
            const stats = await response.json();

            if (stats.success) {
                document.getElementById('pending-requests-count').textContent = stats.pending_requests;
                document.getElementById('approved-this-month-count').textContent = stats.approved_this_month;
                document.getElementById('team-members-count').textContent = stats.team_members;
            }
        } catch (error) {
            console.error("Error fetching dashboard stats: " + error);
        }
    };

    let isRendering = false;
    let currentCalendarMonth = new Date().getMonth() + 1;
    let currentCalendarYear = new Date().getFullYear();
 
    const renderLeaveCalendar = async (month, year) => {
        console.log(`renderLeaveCalendar called with month: ${month}, year: ${year}, isRendering: ${isRendering}`);
        if (isRendering) {
            console.log('renderLeaveCalendar already running, returning');
            return; // Prevent re-rendering if already in progress
        }

        isRendering = true;
        const calendarWidget = document.getElementById('calendar-widget');
        const calendarGrid = calendarWidget.querySelector('.calendar-grid');
        try {
            if (!calendarWidget) {
                console.log('calendarWidget not found, returning');
                isRendering = false;
                return;
            }

            if (!calendarGrid) {
                console.log('calendarGrid not found, returning');
                isRendering = false;
                return;
            }

            const initialDaysCount = calendarGrid.querySelectorAll('.calendar-day').length;
            console.log(`Initial number of calendar days: ${initialDaysCount}`);

            // Clear all dynamically added calendar day cells, preserving the static headers
            const days = calendarGrid.querySelectorAll('.calendar-day');
            days.forEach(day => day.remove());

            const response = await fetch(`${BASE_URL}/api/get_leave_calendar.php?month=${month}&year=${year}`);
            const data = await response.json();

            if (data.success) {
                currentCalendarMonth = data.current_month || month;
                currentCalendarYear = data.current_year || year;

                const leaveEvents = data.leave_events;
                const firstDayOfMonth = new Date(currentCalendarYear, currentCalendarMonth - 1, 1);
                const daysInMonth = new Date(currentCalendarYear, currentCalendarMonth, 0).getDate();
                const startDay = firstDayOfMonth.getDay();

                const calendarHeader = calendarWidget.querySelector('.calendar-header h3');
                if (calendarHeader) {
                    const monthName = firstDayOfMonth.toLocaleString('default', { month: 'long' });
                    calendarHeader.textContent = `${monthName} ${currentCalendarYear}`;
                }

                const fragment = document.createDocumentFragment();

                // Add empty days for the start of the month
                for (let i = 0; i < startDay; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.classList.add('calendar-day', 'empty');
                    fragment.appendChild(emptyDay);
                }

                // Add days of the month
                for (let day = 1; day <= daysInMonth; day++) {
                    const dateStr = `${currentCalendarYear}-${String(currentCalendarMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const leaveEventForDay = leaveEvents.find(event => event.date === dateStr);
                    const usersOnLeave = leaveEventForDay ? leaveEventForDay.users_on_leave : [];

                    const calendarDay = document.createElement('div');
                    calendarDay.classList.add('calendar-day');

                    const dayNumber = document.createElement('span');
                    dayNumber.classList.add('day-number');
                    dayNumber.textContent = day;
                    calendarDay.appendChild(dayNumber);

                    const dateObj = new Date(currentCalendarYear, currentCalendarMonth - 1, day);
                    if (dateObj.getDay() === 0 || dateObj.getDay() === 6) {
                        calendarDay.classList.add('weekend');
                    }

                    if (usersOnLeave.length > 0) {
                        calendarDay.classList.add('has-leave');
                        const leaveEntriesDiv = document.createElement('div');
                        leaveEntriesDiv.classList.add('leave-entries');
                        usersOnLeave.forEach(userLeave => {
                            const leaveEntry = document.createElement('div');
                            leaveEntry.classList.add('leave-entry');
                            leaveEntry.textContent = userLeave.user_name;
                            leaveEntriesDiv.appendChild(leaveEntry);
                        });
                        calendarDay.appendChild(leaveEntriesDiv);
                    }
                    fragment.appendChild(calendarDay);
                }
                calendarGrid.appendChild(fragment);
            } else {
                console.error("Failed to load leave calendar from API: " + data.message);
            }
        } catch (error) {
            console.error("Error fetching or processing leave calendar: " + error);
        } finally {
            if (calendarGrid) {
                const finalDaysCount = calendarGrid.querySelectorAll('.calendar-day').length;
                console.log(`Final number of calendar days: ${finalDaysCount}`);
            }
            isRendering = false; // Release the guard
        }
    };

    // Event listeners for calendar navigation buttons
    const prevMonthBtn = document.getElementById('prevMonth');
    const nextMonthBtn = document.getElementById('nextMonth');

    // Execute dashboard enhancements if on the dashboard page
    if (document.getElementById('dashboard-stats')) {
        updateDashboardStats();
        renderLeaveCalendar(currentCalendarMonth, currentCalendarYear);
    } else if (document.getElementById('calendar-widget')) {
        renderLeaveCalendar(currentCalendarMonth, currentCalendarYear);
    }

    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', () => {
            currentCalendarMonth--;
            if (currentCalendarMonth < 1) {
                currentCalendarMonth = 12;
                currentCalendarYear--;
            }
            renderLeaveCalendar(currentCalendarMonth, currentCalendarYear);
        });
    }

    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', () => {
            currentCalendarMonth++;
            if (currentCalendarMonth > 12) {
                currentCalendarMonth = 1;
                currentCalendarYear++;
            }
            renderLeaveCalendar(currentCalendarMonth, currentCalendarYear);
        });
    }
});