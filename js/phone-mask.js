(function () {
    function formatPhone(value) {
        const digits = value.replace(/\D/g, '').slice(0, 11);
        if (digits.length <= 2) return digits;

        const areaCode = digits.slice(0, 2);
        const number = digits.slice(2);
        if (number.length > 8) {
            return `(${areaCode}) ${number.slice(0, 5)}-${number.slice(5)}`;
        }

        return `(${areaCode}) ${number.slice(0, 4)}${number.length > 4 ? `-${number.slice(4)}` : ''}`;
    }

    document.querySelectorAll('input[type="tel"][name="parent_phone"], input[type="tel"][name="parent_phone_2"]').forEach((input) => {
        input.value = formatPhone(input.value);
        input.addEventListener('input', () => {
            input.value = formatPhone(input.value);
        });
    });

    document.querySelectorAll('[data-phone-clear]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.phoneClear);
            if (input) {
                input.value = '';
                input.focus();
            }
        });
    });
})();
