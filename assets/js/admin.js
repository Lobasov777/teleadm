/* From monitor.php */
document.addEventListener('DOMContentLoaded', function() {
        // Анимация элементов при загрузке
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, { threshold: 0.1 });

        // Наблюдаем за элементами
        document.querySelectorAll('.monitoring-card, .filters-section, .chart-section, .activity-table, .top-users-card').forEach(el => {
            observer.observe(el);
        });

        // График активности
        const ctx = document.getElementById('activityChart');
        if (ctx) {
            const chartData = <?php echo json_encode($chartData); ob_end_flush();
?>;
            const chartLabels = <?php echo json_encode($chartLabels); ob_end_flush();
?>;
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Размещений',
                        data: chartData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            borderColor: '#3b82f6',
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                title: function(context) {
                                    return 'Время: ' + context[0].label;
                                },
                                label: function(context) {
                                    return 'Размещений: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(226, 232, 240, 0.5)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12,
                                    weight: '500'
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(226, 232, 240, 0.5)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12,
                                    weight: '500'
                                },
                                callback: function(value) {
                                    return Number.isInteger(value) ? value : '';
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        }

        // Автообновление каждые 30 секунд
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);

        // Остановка автообновления при скрытии страницы
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                // Можно добавить логику для остановки обновлений
            }
        });
    });

    // Обработка кликов по карточкам статистики
    document.querySelectorAll('.monitoring-card').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function() {
            // Можно добавить переходы к детальной статистике
            const cardType = this.classList[1]; // active-users, campaigns, etc.
            console.log('Клик по карточке:', cardType);
        });
    });

/* From logs.php */
document.addEventListener('DOMContentLoaded', function() {
    // Анимация появления элементов
    const animateElements = () => {
        const elements = document.querySelectorAll('.stat-card, .activity-card, .filters-card, .logs-table-card');
        elements.forEach(element => {
            element.classList.add('animate-in');
        });
    };
    
    // Запускаем анимацию с небольшой задержкой
    setTimeout(animateElements, 100);
    
    // Автоматическая отправка формы при изменении фильтров
    const filterSelects = document.querySelectorAll('.form-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Подсветка строк таблицы при наведении
    const tableRows = document.querySelectorAll('.logs-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.01)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = 'none';
        });
    });
});

/* From index.php */
// Данные для графиков
    const chartLabels = <?php echo json_encode($chartLabels); ob_end_flush();
?>;
    const registrationData = <?php echo json_encode($registrationChartData); ob_end_flush();
?>;
    const revenueData = <?php echo json_encode($revenueChartData); ob_end_flush();
?>;

    // Анимации при загрузке
    document.addEventListener('DOMContentLoaded', function() {
        // Анимация карточек
        const statCards = document.querySelectorAll('.stat-card');
        const quickActions = document.querySelectorAll('.quick-action');
        const dataCards = document.querySelectorAll('.data-card');
        const chartCards = document.querySelectorAll('.chart-card');

        function animateElements(elements, baseDelay = 0) {
            elements.forEach((element, index) => {
                setTimeout(() => {
                    element.classList.add('animate-in');
                }, baseDelay + (index * 100));
            });
        }

        // Запуск анимаций
        setTimeout(() => animateElements(statCards), 100);
        setTimeout(() => animateElements(quickActions), 400);
        setTimeout(() => animateElements(chartCards), 600);
        setTimeout(() => animateElements(dataCards), 700);

        // Инициализация графика
        setTimeout(initChart, 800);
    });

    // Современный мягкий график
    function initChart() {
        const canvas = document.getElementById('mainChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const rect = canvas.parentElement.getBoundingClientRect();
        
        // Устанавливаем размеры с учетом DPI
        const dpr = window.devicePixelRatio || 1;
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';

        const padding = 60;
        const chartWidth = rect.width - padding * 2;
        const chartHeight = rect.height - padding * 2;
        
        const maxReg = Math.max(...registrationData, 1);
        const maxRev = Math.max(...revenueData, 1);
        const stepX = chartWidth / (chartLabels.length - 1);
        
        // Очистка
        ctx.clearRect(0, 0, rect.width, rect.height);
        
        // Мягкая сетка
        ctx.strokeStyle = 'rgba(148, 163, 184, 0.3)';
        ctx.lineWidth = 1;
        
        for (let i = 0; i <= 4; i++) {
            const y = padding + (chartHeight / 4) * i;
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(padding + chartWidth, y);
            ctx.stroke();
        }

        // График доходов (мягкая область)
        if (revenueData.some(v => v > 0)) {
            const gradient = ctx.createLinearGradient(0, padding, 0, padding + chartHeight);
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.2)');
            gradient.addColorStop(1, 'rgba(139, 92, 246, 0.02)');
            
            ctx.beginPath();
            ctx.moveTo(padding, padding + chartHeight);
            
            revenueData.forEach((value, index) => {
                const x = padding + index * stepX;
                const y = padding + chartHeight - (value / maxRev) * chartHeight;
                
                if (index === 0) {
                    ctx.lineTo(x, y);
                } else {
                    // Мягкие кривые
                    const prevX = padding + (index - 1) * stepX;
                    const prevY = padding + chartHeight - (revenueData[index - 1] / maxRev) * chartHeight;
                    const cpX = (prevX + x) / 2;
                    ctx.quadraticCurveTo(cpX, prevY, x, y);
                }
            });
            
            ctx.lineTo(padding + chartWidth, padding + chartHeight);
            ctx.closePath();
            ctx.fillStyle = gradient;
            ctx.fill();
            
            // Мягкая линия доходов
            ctx.strokeStyle = '#8b5cf6';
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            
            revenueData.forEach((value, index) => {
                const x = padding + index * stepX;
                const y = padding + chartHeight - (value / maxRev) * chartHeight;
                
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    const prevX = padding + (index - 1) * stepX;
                    const prevY = padding + chartHeight - (revenueData[index - 1] / maxRev) * chartHeight;
                    const cpX = (prevX + x) / 2;
                    ctx.quadraticCurveTo(cpX, prevY, x, y);
                }
            });
            
            ctx.stroke();
        }

        // График регистраций (мягкая линия)
        if (registrationData.some(v => v > 0)) {
            ctx.strokeStyle = '#3b82f6';
            ctx.lineWidth = 3;
            ctx.beginPath();
            
            registrationData.forEach((value, index) => {
                const x = padding + index * stepX;
                const y = padding + chartHeight - (value / maxReg) * chartHeight;
                
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    const prevX = padding + (index - 1) * stepX;
                    const prevY = padding + chartHeight - (registrationData[index - 1] / maxReg) * chartHeight;
                    const cpX = (prevX + x) / 2;
                    ctx.quadraticCurveTo(cpX, prevY, x, y);
                }
            });
            
            ctx.stroke();
            
            // Мягкие точки
            ctx.fillStyle = '#3b82f6';
            registrationData.forEach((value, index) => {
                const x = padding + index * stepX;
                const y = padding + chartHeight - (value / maxReg) * chartHeight;
                
                ctx.beginPath();
                ctx.arc(x, y, 5, 0, Math.PI * 2);
                ctx.fill();
                
                // Белый центр
                ctx.beginPath();
                ctx.arc(x, y, 2, 0, Math.PI * 2);
                ctx.fillStyle = 'white';
                ctx.fill();
                ctx.fillStyle = '#3b82f6';
            });
        }
        
        // Мягкие подписи
        ctx.fillStyle = '#64748b';
        ctx.font = '500 12px Inter, system-ui';
        ctx.textAlign = 'center';
        
        // Подписи дней
        chartLabels.forEach((label, index) => {
            const x = padding + index * stepX;
            ctx.fillText(label, x, padding + chartHeight + 25);
        });
        
        // Подписи значений регистраций (левая ось)
        ctx.textAlign = 'right';
        ctx.fillStyle = '#3b82f6';
        for (let i = 0; i <= 4; i++) {
            const value = Math.round((maxReg / 4) * (4 - i));
            const y = padding + (chartHeight / 4) * i + 4;
            ctx.fillText(value.toString(), padding - 15, y);
        }
        
        // Подписи значений доходов (правая ось)
        ctx.textAlign = 'left';
        ctx.fillStyle = '#8b5cf6';
        for (let i = 0; i <= 4; i++) {
            const value = Math.round((maxRev / 4) * (4 - i));
            const y = padding + (chartHeight / 4) * i + 4;
            ctx.fillText('₽' + value.toLocaleString(), padding + chartWidth + 15, y);
        }

        // Элегантная легенда
        ctx.font = '600 14px Inter, system-ui';
        ctx.textAlign = 'left';
        
        // Регистрации
        ctx.fillStyle = '#3b82f6';
        ctx.beginPath();
        ctx.arc(padding + 10, 25, 6, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillText('Новые пользователи', padding + 25, 30);
        
        // Доходы
        ctx.fillStyle = '#8b5cf6';
        ctx.beginPath();
        ctx.arc(padding + 180, 25, 6, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillText('Доходы (₽)', padding + 195, 30);
    }

    // Обновление графика при изменении размера
    window.addEventListener('resize', function() {
        setTimeout(initChart, 100);
    });

/* From payments.php */
// Анимации
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.fade-in').forEach(el => {
        observer.observe(el);
    });

/* From settings.php */
document.addEventListener('DOMContentLoaded', function() {
        // Анимация элементов при загрузке
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, { threshold: 0.1 });

        // Наблюдаем за элементами
        document.querySelectorAll('.settings-section, .overview-card').forEach(el => {
            observer.observe(el);
        });

        // Подтверждение для чекбоксов критических настроек
        const criticalCheckboxes = ['maintenance_mode'];
        criticalCheckboxes.forEach(checkboxId => {
            const checkbox = document.getElementById(checkboxId);
            if (checkbox) {
                checkbox.addEventListener('change', function() {
                    if (this.checked && checkboxId === 'maintenance_mode') {
                        if (!confirm('Включить режим обслуживания? Сайт станет недоступен для пользователей.')) {
                            this.checked = false;
                        }
                    }
                });
            }
        });

        // Валидация формы
        const form = document.querySelector('.settings-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const premiumPrice = document.querySelector('input[name="premium_price"]').value;
                const premiumDuration = document.querySelector('input[name="premium_duration_days"]').value;
                
                if (premiumPrice < 1 || premiumPrice > 99999) {
                    e.preventDefault();
                    alert('Цена Premium должна быть от 1 до 99999 рублей');
                    return;
                }
                
                if (premiumDuration < 1 || premiumDuration > 365) {
                    e.preventDefault();
                    alert('Длительность Premium должна быть от 1 до 365 дней');
                    return;
                }
            });
        }
    });

/* From users.php */
// Dropdown меню
    function toggleDropdown(userId) {
        // Закрываем все открытые dropdown
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if (menu.id !== `dropdown-${userId}`) {
                menu.classList.remove('show');
            }
        });
        
        // Переключаем текущий
        const dropdown = document.getElementById(`dropdown-${userId}`);
        dropdown.classList.toggle('show');
    }

    // Изменение роли
    function changeRole(userId, currentRole, username) {
        document.getElementById('roleUserId').value = userId;
        document.getElementById('oldRole').value = currentRole;
        document.getElementById('roleUserName').textContent = username;
        
        // Устанавливаем противоположную роль по умолчанию
        const newRoleSelect = document.getElementById('newRole');
        newRoleSelect.value = currentRole === 'premium' ? 'user' : 'premium';
        
        document.getElementById('roleModal').classList.add('show');
        closeAllDropdowns();
    }

    // Блокировка пользователя
    function blockUser(userId, username) {
        document.getElementById('blockUserId').value = userId;
        document.getElementById('blockUserName').textContent = username;
        document.getElementById('blockModal').classList.add('show');
        closeAllDropdowns();
    }

    // Разблокировка пользователя
    function unblockUser(userId, username) {
        document.getElementById('unblockUserId').value = userId;
        document.getElementById('unblockUserName').textContent = username;
        document.getElementById('unblockModal').classList.add('show');
        closeAllDropdowns();
    }

    // Вход как пользователь (функция для будущей реализации)
    function loginAsUser(userId) {
        if (confirm('Войти от имени этого пользователя? Вы будете перенаправлены в его кабинет.')) {
            // Здесь можно реализовать функционал входа под пользователем
            window.open(`../dashboard/?impersonate=${userId}`, '_blank');
        }
        closeAllDropdowns();
    }

    // Закрытие модальных окон
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    // Закрытие всех dropdown меню
    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }

    // События
    document.addEventListener('DOMContentLoaded', function() {
        // Анимация элементов при загрузке
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, { threshold: 0.1 });

        // Наблюдаем за элементами
        document.querySelectorAll('.stat-card, .filters-card, .users-table-card').forEach(el => {
            observer.observe(el);
        });

        // Закрытие dropdown при клике вне
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.actions-dropdown')) {
                closeAllDropdowns();
            }
        });

        // Закрытие модальных окон при клике вне или по Escape
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
                closeAllDropdowns();
            }
        });

        // Автофокус на первое поле в модальных окнах
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('transitionend', function() {
                if (modal.classList.contains('show')) {
                    const firstInput = modal.querySelector('input, select, textarea');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }
            });
        });
    });

    // Автосохранение фильтров в localStorage
    function saveFilters() {
        const filters = {
            search: document.querySelector('input[name="search"]').value,
            role: document.querySelector('select[name="role"]').value,
            status: document.querySelector('select[name="status"]').value,
            sort: document.querySelector('select[name="sort"]').value
        };
        localStorage.setItem('userFilters', JSON.stringify(filters));
    }

    // Восстановление фильтров
    function restoreFilters() {
        const saved = localStorage.getItem('userFilters');
        if (saved) {
            const filters = JSON.parse(saved);
            Object.keys(filters).forEach(key => {
                const element = document.querySelector(`[name="${key}"]`);
                if (element && filters[key]) {
                    element.value = filters[key];
                }
            });
        }
    }

    // Восстанавливаем фильтры при загрузке
    document.addEventListener('DOMContentLoaded', restoreFilters);