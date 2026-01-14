import('./delivery-status.scss');


(function() {
    'use strict';

    function addUndeliverableClass() {
        const allStateElements = document.querySelectorAll('[id="state"]');

        allStateElements.forEach((stateDiv, index) => {
            const textContent = stateDiv.textContent || '';

            const isDeliveryStatus = textContent.includes('Lieferstatus') ||
                stateDiv.querySelector('label[for*="delivery"]') ||
                stateDiv.closest('.sw-order-state-select-v2__order_delivery');

            if (!isDeliveryStatus) return;

            const inputs = stateDiv.querySelectorAll('input');

            let hasUndeliverable = textContent.includes('Unzustellbar') || textContent.includes('Undeliverable');

            inputs.forEach((input, inputIndex) => {
                const placeholder = input.placeholder || '';
                const value = input.value || '';
                const ariaLabel = input.getAttribute('aria-label') || '';

                if (placeholder.includes('Unzustellbar') || placeholder.includes('Undeliverable') ||
                    value.includes('Unzustellbar') || value.includes('Undeliverable') ||
                    ariaLabel.includes('Unzustellbar') || ariaLabel.includes('Undeliverable')) {
                    hasUndeliverable = true;
                }
            });

            if (hasUndeliverable) {
                stateDiv.classList.add('undeliverable');
            } else {
                stateDiv.classList.remove('undeliverable');
            }
        });
    }

    const observer = new MutationObserver(() => {
        setTimeout(addUndeliverableClass, 50);
    });

    function init() {
        addUndeliverableClass();

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['placeholder', 'value', 'aria-label']
        });

        document.addEventListener('click', () => setTimeout(addUndeliverableClass, 100));
        document.addEventListener('change', () => setTimeout(addUndeliverableClass, 100));
        document.addEventListener('input', () => setTimeout(addUndeliverableClass, 100));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
