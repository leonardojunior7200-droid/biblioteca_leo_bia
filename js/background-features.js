// Background Features Manager for Biblioteca Escolar
// Manages theme, background, gallery, and removal functionality

class BackgroundFeaturesManager {
    constructor() {
        this.init();
    }

    init() {
        this.cacheDOMElements();
        this.loadInitialState();
        this.setupEventListeners();
        this.setupGallery();
    }

    cacheDOMElements() {
        this.elements = {
            themeToggle: document.getElementById('theme-toggle'),
            backgroundToggle: document.getElementById('background-toggle'),
            photoButton: document.getElementById('background-photo-button'),
            galleryToggle: document.getElementById('background-gallery-toggle'),
            removerToggle: document.getElementById('background-remover-toggle'),
            photoInput: document.getElementById('background-photo-input'),
            gallery: document.getElementById('background-gallery'),
            galleryClose: document.getElementById('background-gallery-close'),
            galleryList: document.getElementById('background-gallery-list'),
            galleryEmpty: document.querySelector('.background-gallery-empty'),
            removerModal: document.getElementById('background-remover-modal'),
            removerClose: document.querySelector('.bg-remover-close'),
            removerCancel: document.getElementById('bg-remover-cancel'),
            removerApply: document.getElementById('bg-remover-apply'),
            preview: document.querySelector('.bg-remover-preview'),
            thresholdInput: document.getElementById('bg-remover-threshold'),
            thresholdValue: document.getElementById('bg-remover-threshold-value'),
            marginInput: document.getElementById('bg-remover-margin'),
            body: document.body,
            root: document.documentElement
        };
        
        // Create processor instance
        this.processor = window.BackgroundProcessor;
        
        // Current state
        this.state = {
            activeBackground: 'default',
            currentImage: null,
            savedBackgrounds: this.loadSavedBackgrounds()
        };
    }

    loadInitialState() {
        // Load theme
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = savedTheme === 'dark' || (!savedTheme && prefersDark);
        
        this.elements.root.classList.toggle('theme-dark', isDark);
        this.elements.body.classList.toggle('theme-dark', isDark);
        
        // Load background
        const savedBackground = localStorage.getItem('background');
        const savedBackgroundImage = localStorage.getItem('backgroundImage');
        const isStudent = this.elements.body.classList.contains('student-dashboard-page');
        const backgroundOptions = isStudent
            ? ['default', 'light', 'dark', 'blue']
            : ['default', 'light', 'dark', 'blue', 'custom'];
        
        if (isStudent && savedBackground === 'custom') {
            localStorage.removeItem('background');
            localStorage.removeItem('backgroundImage');
            this.applyBackground('default');
            this.state.activeBackground = 'default';
        } else if (savedBackground === 'custom' && savedBackgroundImage && !isStudent) {
            this.applyBackground('custom', savedBackgroundImage);
            this.state.activeBackground = 'custom';
        } else if (backgroundOptions.includes(savedBackground)) {
            this.applyBackground(savedBackground);
            this.state.activeBackground = savedBackground;
        } else {
            this.applyBackground('default');
            this.state.activeBackground = 'default';
        }
    }

    setupEventListeners() {
        if (this.elements.themeToggle) {
            this.elements.themeToggle.addEventListener('click', () => this.toggleTheme());
        }
        
        if (this.elements.backgroundToggle) {
            this.elements.backgroundToggle.addEventListener('click', () => this.cycleBackground());
        }
        
        if (this.elements.photoButton && this.elements.photoInput) {
            this.elements.photoButton.addEventListener('click', () => this.elements.photoInput.click());
            this.elements.photoInput.addEventListener('change', (e) => this.handlePhotoUpload(e));
        }
        
        if (this.elements.galleryToggle) {
            this.elements.galleryToggle.addEventListener('click', () => this.openGallery());
        }
        
        if (this.elements.galleryClose) {
            this.elements.galleryClose.addEventListener('click', () => this.closeGallery());
        }
        
        if (this.elements.removerToggle) {
            this.elements.removerToggle.addEventListener('click', () => this.openBackgroundRemover());
        }
        
        if (this.elements.removerClose) {
            this.elements.removerClose.addEventListener('click', () => this.closeBackgroundRemover());
        }
        
        if (this.elements.removerCancel) {
            this.elements.removerCancel.addEventListener('click', () => this.closeBackgroundRemover());
        }
        
        if (this.elements.removerApply) {
            this.elements.removerApply.addEventListener('click', () => this.applyBackgroundRemoval());
        }
        
        if (this.elements.removerModal) {
            this.elements.removerModal.addEventListener('click', (e) => {
                if (e.target === this.elements.removerModal) {
                    this.closeBackgroundRemover();
                }
            });
        }
        
        if (this.elements.gallery) {
            this.elements.gallery.addEventListener('click', (e) => {
                if (e.target === this.elements.gallery) {
                    this.closeGallery();
                }
            });
        }
        
        // Threshold input
        if (this.elements.thresholdInput) {
            this.elements.thresholdInput.addEventListener('input', (e) => {
                if (this.elements.thresholdValue) {
                    this.elements.thresholdValue.textContent = e.target.value;
                }
                this.updateApplyButtonState();
            });
        }
        
        // Margin input
        if (this.elements.marginInput) {
            this.elements.marginInput.addEventListener('input', () => {
                this.updateApplyButtonState();
            });
        }
    }

    setupGallery() {
        // Populate gallery with saved backgrounds
        this.updateGallery();
    }

    toggleTheme() {
        const isDark = this.elements.root.classList.toggle('theme-dark');
        this.elements.body.classList.toggle('theme-dark', isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        this.elements.themeToggle.textContent = isDark ? 'Tema claro' : 'Tema escuro';
    }

    cycleBackground() {
        const backgroundOptions = this.elements.body.classList.contains('student-dashboard-page')
            ? ['default', 'light', 'dark', 'blue']
            : ['default', 'light', 'dark', 'blue', 'custom'];
        const currentIndex = backgroundOptions.indexOf(this.state.activeBackground);
        const nextIndex = (currentIndex + 1) % backgroundOptions.length;
        const nextOption = backgroundOptions[nextIndex];
        
        this.applyBackground(nextOption);
        this.state.activeBackground = nextOption;
    }

    applyBackground(type, imageUrl = null) {
        this.elements.body.classList.remove('no-image', 'background-light', 'background-dark', 'background-blue', 'custom-background');
        this.elements.body.style.background = '';

        if (type === 'custom' && imageUrl) {
            this.elements.body.classList.add('custom-background');
            this.elements.body.style.background = `linear-gradient(rgba(7, 18, 34, 0.5), rgba(7, 18, 34, 0.5)), url('${imageUrl}') center/cover fixed`;
            localStorage.setItem('backgroundImage', imageUrl);
        } else {
            if (type === 'default') {
                this.elements.body.classList.add('no-image');
            } else {
                this.elements.body.classList.add('background-' + type);
            }
            localStorage.setItem('background', type);
            localStorage.removeItem('backgroundImage');
        }

        this.updateBackgroundToggleText();
    }

    updateBackgroundToggleText() {
        if (!this.elements.backgroundToggle) return;
        
        const backgroundLabels = {
            default: 'Fundo: padrão',
            light: 'Fundo: claro',
            dark: 'Fundo: escuro',
            blue: 'Fundo: azul',
            custom: 'Fundo: personalizado'
        };
        
        this.elements.backgroundToggle.textContent = 'Fundo: ' + (backgroundLabels[this.state.activeBackground] || 'padrão');
    }

    loadSavedBackgrounds() {
        return JSON.parse(localStorage.getItem('savedBackgrounds') || '[]');
    }

    saveBackground(dataUrl) {
        const savedImages = this.loadSavedBackgrounds();
        if (!savedImages.includes(dataUrl)) {
            savedImages.push(dataUrl);
            localStorage.setItem('savedBackgrounds', JSON.stringify(savedImages));
        }
    }

    updateGallery() {
        const savedImages = this.loadSavedBackgrounds();
        this.elements.galleryList.innerHTML = '';
        
        if (!savedImages.length) {
            this.elements.galleryEmpty.style.display = 'block';
            return;
        }
        
        this.elements.galleryEmpty.style.display = 'none';
        
        savedImages.forEach((imageUrl, index) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'background-gallery-card';
            const image = document.createElement('img');
            image.src = imageUrl;
            image.alt = `Fundo salvo ${index + 1}`;
            const label = document.createElement('span');
            label.textContent = `Fundo ${index + 1}`;
            card.append(image, label);
            card.addEventListener('click', () => {
                this.applyBackground('custom', imageUrl);
                this.closeGallery();
            });
            this.elements.galleryList.appendChild(card);
        });
    }

    openGallery() {
        this.elements.gallery.classList.remove('hidden');
        this.updateGallery();
    }

    closeGallery() {
        this.elements.gallery.classList.add('hidden');
    }

    openBackgroundRemover() {
        this.elements.removerModal.classList.remove('hidden');
        this.resetPreview();
    }

    closeBackgroundRemover() {
        this.elements.removerModal.classList.add('hidden');
        this.resetPreview();
        this.state.currentImage = null;
    }

    handlePhotoUpload(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = (event) => {
            const imageUrl = event.target.result;
            const img = new Image();
            img.onload = () => {
                this.elements.preview.innerHTML = '';
                this.elements.preview.appendChild(img);
                this.elements.preview.classList.remove('bg-remover-preview-empty');
                this.elements.removerApply.disabled = false;
                this.elements.removerModal.querySelector('.bg-remover-controls').style.display = 'grid';
                this.state.currentImage = img;
                this.updateApplyButtonState();
            };
            img.src = imageUrl;
        };
        reader.readAsDataURL(file);
    }

    resetPreview() {
        this.elements.preview.innerHTML = '<p class="bg-remover-preview-empty-text">Carregue uma imagem para remover o fundo. Use o botão "Fundo personalizado" para selecionar uma imagem.</p>';
        this.elements.preview.classList.add('bg-remover-preview-empty');
        this.elements.removerApply.disabled = true;
        this.elements.removerModal.querySelector('.bg-remover-controls').style.display = 'none';
    }

    updateApplyButtonState() {
        this.elements.removerApply.disabled = !this.state.currentImage;
    }

    applyBackgroundRemoval() {
        if (!this.state.currentImage) return;
        
        const threshold = parseInt(this.elements.thresholdInput.value);
        const margin = parseInt(this.elements.marginInput.value);
        
        // Show processing state
        this.elements.preview.innerHTML = '';
        const processingDiv = document.createElement('div');
        processingDiv.className = 'bg-remover-processing';
        processingDiv.innerHTML = `
            <div class="bg-remover-spinner"></div>
            <p>Removendo fundo...</p>
        `;
        this.elements.preview.appendChild(processingDiv);
        
        // Process image
        this.processor.processImageUrl(this.state.currentImage.src, {
            threshold: threshold,
            margin: margin,
            replaceColor: null
        })
        .then(dataUrl => {
            // Save to custom backgrounds
            this.saveBackground(dataUrl);
            
            // Apply as custom background
            this.applyBackground('custom', dataUrl);
            
            // Update preview
            this.elements.preview.innerHTML = '';
            const resultImg = new Image();
            resultImg.onload = () => {
                this.elements.preview.innerHTML = '';
                this.elements.preview.appendChild(resultImg);
                this.elements.preview.classList.remove('bg-remover-preview-empty');
            };
            resultImg.src = dataUrl;
            
            // Auto-close after success
            setTimeout(() => {
                this.closeBackgroundRemover();
                this.showSuccessMessage();
            }, 1500);
        })
        .catch(error => {
            console.error('Error processing image:', error);
            this.showErrorMessage(error.message);
            this.closeBackgroundRemover();
        });
    }

    showSuccessMessage() {
        // Create a temporary success message
        const message = document.createElement('div');
        message.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--flash-success-bg);
            border: 1px solid var(--flash-success-border);
            padding: 20px;
            border-radius: 8px;
            z-index: 2000;
            color: var(--text);
            font-weight: 600;
        `;
        message.textContent = 'Fundo removido com sucesso! Salvo como fundo personalizado.';
        document.body.appendChild(message);
        
        setTimeout(() => {
            document.body.removeChild(message);
        }, 3000);
    }

    showErrorMessage(message) {
        // Create a temporary error message
        const messageDiv = document.createElement('div');
        messageDiv.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--flash-error-bg);
            border: 1px solid var(--flash-error-border);
            padding: 20px;
            border-radius: 8px;
            z-index: 2000;
            color: var(--text);
            font-weight: 600;
        `;
        messageDiv.textContent = 'Erro ao processar imagem: ' + message;
        document.body.appendChild(messageDiv);
        
        setTimeout(() => {
            document.body.removeChild(messageDiv);
        }, 3000);
    }

    // Public method to manually trigger background removal
    static initGlobal() {
        // Create a new instance
        new BackgroundFeaturesManager();
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        BackgroundFeaturesManager.initGlobal();
    });
} else {
    BackgroundFeaturesManager.initGlobal();
}