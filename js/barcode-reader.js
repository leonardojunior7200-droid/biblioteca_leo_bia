(function () {
    const input = document.getElementById('barcode');
    const focusButton = document.getElementById('barcode-focus');
    const status = document.getElementById('barcode-status');

    if (!input) {
        return;
    }

    function activateReader() {
        input.focus();
        input.select();
        if (status) {
            status.textContent = 'Leitura ativa. Passe o leitor agora; o código será mantido no cadastro.';
        }
    }

    if (focusButton) {
        focusButton.addEventListener('click', activateReader);
    }

    input.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        input.value = input.value.replace(/[\r\n]/g, '').trim();
        if (status) {
            status.textContent = input.value
                ? 'Código capturado. Salve o cadastro para registrar o livro.'
                : 'Nenhum código foi capturado. Passe o leitor novamente.';
        }
    });

    input.addEventListener('input', function () {
        if (status && input.value.trim() !== '') {
            status.textContent = 'Código recebido. Aguarde o bip ou clique em Salvar para registrar.';
        }
    });
})();
