(function () {

            const boxes = document.querySelectorAll('[data-resend-seconds]');

            boxes.forEach((box) => {

                        const button = box.querySelector('[data-resend-button]');

                        const label = box.querySelector('[data-resend-label]');

                        if (!button || !label)
                            return;

                        let seconds = Number.parseInt(box.getAttribute('data-resend-seconds') || '0', 10);

                        if (!Number.isFinite(seconds) || seconds <= 0) {

                                    button.disabled = false;

                                    label.textContent = 'Reenviar código';

                                    return;

        }

                        button.disabled = true;

                        const render = () => {

                                    if (seconds <= 0) {

                                                button.disabled = false;

                                                label.textContent = 'Reenviar código';

                                                return;

            }

                                    if (seconds < 60) {

                                                label.textContent = `Reenviar em ${seconds}s`;

            }
                                    else {

                                                const minutes = Math.floor(seconds / 60);

                                                const remainingSeconds = seconds % 60;

                                                label.textContent =
                                                    `Reenviar em ${minutes}:${String(remainingSeconds).padStart(2, '0')}`;

            }

                                    seconds -= 1;

                                    window.setTimeout(render, 1000);

        };

                        render();

    });

})();
