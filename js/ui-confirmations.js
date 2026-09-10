(function () {
    const modal = document.getElementById('confirmation-modal');
    const title = document.getElementById('confirmation-title');
    const message = document.getElementById('confirmation-message');
    const cancelButton = document.getElementById('confirmation-cancel');
    const acceptButton = document.getElementById('confirmation-accept');

    if (!modal || !title || !message || !cancelButton || !acceptButton) {
        return;
    }

    let pendingAction = null;

    function closeModal() {
        modal.classList.add('hidden');
        pendingAction = null;
    }

    function openModal(actionTitle, actionMessage, action) {
        title.textContent = actionTitle;
        message.textContent = actionMessage;
        pendingAction = action;
        modal.classList.remove('hidden');
        cancelButton.focus();
    }

    cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    acceptButton.addEventListener('click', () => {
        if (pendingAction) {
            const action = pendingAction;
            closeModal();
            action();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    document.addEventListener('click', (event) => {
        const logoutLink = event.target.closest('a[href*="logout.php"]');
        if (!logoutLink) {
            return;
        }

        event.preventDefault();
        const destination = logoutLink.href;
        openModal(
            'Sair do sistema',
            'Deseja realmente sair do sistema?',
            () => { window.location.href = destination; }
        );
    });

    document.addEventListener('submit', (event) => {
        const deleteButton = event.submitter && event.submitter.dataset.confirmDelete;
        if (!deleteButton) {
            return;
        }

        event.preventDefault();
        const form = event.target;
        openModal(
            'Excluir livro',
            `Tem certeza que deseja excluir o livro "${deleteButton}"? Essa ação não poderá ser desfeita.`,
            () => { form.submit(); }
        );
    });
})();
