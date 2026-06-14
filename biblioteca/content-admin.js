(function () {
    function setStatus(el, message, tone) {
        if (!el) return;
        el.textContent = message;
        el.style.color = tone === 'error' ? '#f55' : 'var(--success, #4ade80)';
    }

    async function postJson(url, payload) {
        const resp = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json; charset=utf-8' },
            body: JSON.stringify(payload),
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || !data.ok) {
            throw new Error(data.error || 'Request failed');
        }
        return data;
    }

    async function deletePage(pageId) {
        const resp = await fetch(`/biblioteca/manage-page.php?page=${encodeURIComponent(pageId)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || !data.ok) {
            throw new Error(data.error || 'Could not delete page');
        }
        return data;
    }

    const toggleAddPageBtn = document.getElementById('toggleAddPageBtn');
    const addPagePanel = document.getElementById('addPagePanel');
    const cancelAddPageBtn = document.getElementById('cancelAddPageBtn');
    const addPageForm = document.getElementById('addPageForm');
    const pageRegistryStatus = document.getElementById('pageRegistryStatus');

    function setAddPagePanelOpen(open) {
        if (!addPagePanel || !toggleAddPageBtn) return;
        addPagePanel.hidden = !open;
        toggleAddPageBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggleAddPageBtn.classList.toggle('active', open);
        if (open) {
            const titleInput = addPageForm?.querySelector('input[name="title"]');
            if (titleInput instanceof HTMLInputElement) {
                titleInput.focus();
            }
        } else if (pageRegistryStatus) {
            pageRegistryStatus.textContent = '';
        }
    }

    toggleAddPageBtn?.addEventListener('click', () => {
        setAddPagePanelOpen(addPagePanel?.hidden !== false);
    });

    cancelAddPageBtn?.addEventListener('click', () => {
        addPageForm?.reset();
        setAddPagePanelOpen(false);
    });

    if (addPageForm) {
        addPageForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(addPageForm);
            const title = String(formData.get('title') || '').trim();

            if (!title) {
                setStatus(pageRegistryStatus, 'Page name is required.', 'error');
                return;
            }

            try {
                setStatus(pageRegistryStatus, 'Creating page…', 'neutral');
                const data = await postJson('/biblioteca/manage-page.php', { title });
                const pageId = data.page?.id || data.pages?.[data.pages.length - 1]?.id;
                if (!pageId) {
                    throw new Error('Page was created but no id was returned.');
                }
                window.location.href = `?tab=content&cntab=pages&page=${encodeURIComponent(pageId)}`;
            } catch (error) {
                setStatus(pageRegistryStatus, '❌ ' + error.message, 'error');
            }
        });
    }

    const deleteCurrentPageBtn = document.getElementById('deleteCurrentPageBtn');
    const pageDeleteModal = document.getElementById('pageDeleteModal');
    const pageDeleteModalName = document.getElementById('pageDeleteModalName');
    const pageDeleteConfirmBtn = document.getElementById('pageDeleteConfirmBtn');
    const pageDeleteCancelBtn = document.getElementById('pageDeleteCancelBtn');
    let pendingDeletePageId = '';

    function openPageDeleteModal(pageId, pageTitle) {
        pendingDeletePageId = pageId;
        if (pageDeleteModalName) {
            pageDeleteModalName.textContent = pageTitle;
        }
        if (pageDeleteModal) {
            pageDeleteModal.style.display = 'flex';
            pageDeleteModal.setAttribute('aria-hidden', 'false');
        }
        pageDeleteConfirmBtn?.focus();
    }

    function closePageDeleteModal() {
        pendingDeletePageId = '';
        if (pageDeleteModal) {
            pageDeleteModal.style.display = 'none';
            pageDeleteModal.setAttribute('aria-hidden', 'true');
        }
    }

    if (deleteCurrentPageBtn) {
        deleteCurrentPageBtn.addEventListener('click', () => {
            const pageId = deleteCurrentPageBtn.dataset.pageId || '';
            const pageTitle = deleteCurrentPageBtn.dataset.pageTitle || 'this page';
            if (!pageId) return;
            openPageDeleteModal(pageId, pageTitle);
        });
    }

    pageDeleteCancelBtn?.addEventListener('click', closePageDeleteModal);
    pageDeleteModal?.addEventListener('click', (event) => {
        if (event.target === pageDeleteModal) {
            closePageDeleteModal();
        }
    });
    pageDeleteConfirmBtn?.addEventListener('click', async () => {
        const pageId = pendingDeletePageId;
        if (!pageId) return;

        try {
            pageDeleteConfirmBtn.disabled = true;
            deleteCurrentPageBtn.disabled = true;
            await deletePage(pageId);
            window.location.href = '?tab=content&cntab=pages&page=faq';
        } catch (error) {
            pageDeleteConfirmBtn.disabled = false;
            if (deleteCurrentPageBtn) {
                deleteCurrentPageBtn.disabled = false;
            }
            alert(error.message);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !pageDeleteModal || pageDeleteModal.style.display !== 'flex') {
            return;
        }
        closePageDeleteModal();
    });

    const savePlayerLayoutBtn = document.getElementById('savePlayerLayoutBtn');
    const playerLayoutStatus = document.getElementById('playerLayoutStatus');
    if (savePlayerLayoutBtn) {
        savePlayerLayoutBtn.addEventListener('click', async () => {
            const galleryEnabled = document.getElementById('playerModuleGallery')?.checked ?? true;
            const pagesEnabled = document.getElementById('playerModulePages')?.checked ?? true;
            const pageRows = document.querySelectorAll('.player-page-layout-row');
            const pages = [];

            pageRows.forEach((row) => {
                const pageId = row.dataset.pageId || '';
                if (!pageId) return;
                const labelInput = row.querySelector('.player-page-label-input');
                const showInput = row.querySelector('.player-page-show-input');
                pages.push({
                    id: pageId,
                    label: labelInput ? labelInput.value : '',
                    show_in_player: showInput ? showInput.checked : false,
                });
            });

            try {
                savePlayerLayoutBtn.disabled = true;
                setStatus(playerLayoutStatus, 'Saving…', 'neutral');
                await postJson('/biblioteca/save-player-layout.php', {
                    modules: {
                        gallery: { enabled: galleryEnabled },
                        pages: { enabled: pagesEnabled },
                    },
                    pages,
                });
                setStatus(playerLayoutStatus, 'Player layout saved', 'ok');
            } catch (error) {
                setStatus(playerLayoutStatus, '❌ ' + error.message, 'error');
            } finally {
                savePlayerLayoutBtn.disabled = false;
            }
        });
    }
})();
