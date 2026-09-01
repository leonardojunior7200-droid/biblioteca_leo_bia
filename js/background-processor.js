// Background Processing Utility for Biblioteca Escolar
// Provides client-side background removal functionality

class BackgroundProcessor {
    constructor() {
        this.canvas = document.createElement('canvas');
        this.ctx = this.canvas.getContext('2d');
    }

    /**
     * Process an image to remove background
     * @param {ImageData} imageData - The image data to process
     * @param {Object} options - Processing options
     * @returns {ImageData} Processed image data with transparency
     */
    removeBackground(imageData, options = {}) {
        const {
            threshold = 30,        // Color distance threshold for background removal
            margin = 0,            // Margin around detected edges
            replaceColor = null    // Color to replace background with (null for transparency)
        } = options;

        const data = imageData.data;
        const width = imageData.width;
        const height = imageData.height;

        // Simple background detection: assume corners represent background
        // Sample pixels from corners to determine background color
        const bgColor = this._estimateBackgroundColor(data, width, height);
        
        // Process each pixel
        for (let i = 0; i < data.length; i += 4) {
            const r = data[i];
            const g = data[i + 1];
            const b = data[i + 2];
            const a = data[i + 3];
            
            // Calculate color distance from background
            const distance = this._colorDistance([r, g, b], bgColor);
            
            if (distance < threshold) {
                // This pixel is considered background
                if (replaceColor) {
                    // Replace with specified color
                    data[i] = replaceColor.r;
                    data[i + 1] = replaceColor.g;
                    data[i + 2] = replaceColor.b;
                    // Keep original alpha or set to opaque
                    data[i + 3] = replaceColor.a !== undefined ? replaceColor.a : 255;
                } else {
                    // Make transparent
                    data[i + 3] = 0;
                }
            } else if (margin > 0 && distance < threshold + margin) {
                // Semi-transparent edge
                const alpha = 255 - Math.floor(((distance - threshold) / margin) * 255);
                data[i + 3] = Math.max(0, Math.min(255, alpha));
            }
            // Else: keep original pixel (foreground)
        }

        return imageData;
    }

    /**
     * Estimate background color by sampling corners
     */
    _estimateBackgroundColor(data, width, height) {
        const samples = [];
        const sampleSize = 10; // Number of pixels to sample from each corner/edge
        
        // Sample from corners and edges
        const positions = [
            // Top-left corner
            {x: 0, y: 0},
            // Top-right corner
            {x: width - 1, y: 0},
            // Bottom-left corner
            {x: 0, y: height - 1},
            // Bottom-right corner
            {x: width - 1, y: height - 1},
            // Top edge middle
            {x: Math.floor(width / 2), y: 0},
            // Bottom edge middle
            {x: Math.floor(width / 2), y: height - 1},
            // Left edge middle
            {x: 0, y: Math.floor(height / 2)},
            // Right edge middle
            {x: width - 1, y: Math.floor(height / 2)}
        ];

        positions.forEach(pos => {
            const index = (pos.y * width + pos.x) * 4;
            if (index >= 0 && index + 3 < data.length) {
                samples.push([
                    data[index],
                    data[index + 1],
                    data[index + 2]
                ]);
            }
        });

        // Average the sampled colors
        if (samples.length === 0) return [255, 255, 255]; // Default to white

        const avg = samples.reduce((acc, color) => {
            return [
                acc[0] + color[0],
                acc[1] + color[1],
                acc[2] + color[2]
            ];
        }, [0, 0, 0]).map(val => Math.floor(val / samples.length));

        return avg;
    }

    /**
     * Calculate Euclidean distance between two RGB colors
     */
    _colorDistance(color1, color2) {
        const dr = color1[0] - color2[0];
        const dg = color1[1] - color2[1];
        const db = color1[2] - color2[2];
        return Math.sqrt(dr * dr + dg * dg + db * db);
    }

    /**
     * Load an image and return a Promise that resolves with ImageData
     */
    loadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'Anonymous'; // Important for CORS
            img.onload = () => {
                this.canvas.width = img.width;
                this.canvas.height = img.height;
                this.ctx.drawImage(img, 0, 0);
                const imageData = this.ctx.getImageData(0, 0, img.width, img.height);
                resolve({imageData, width: img.width, height: img.height, img});
            };
            img.onerror = reject;
            img.src = src;
        });
    }

    /**
     * Process an image URL and return processed ImageData
     */
    processImageUrl(src, options = {}) {
        return this.loadImage(src)
            .then(({imageData}) => {
                const processedData = this.removeBackground(imageData, options);
                this.ctx.putImageData(processedData, 0, 0);
                return this.canvas.toDataURL('image/png');
            });
    }
}

// Global instance for use in the application
window.BackgroundProcessor = new BackgroundProcessor();