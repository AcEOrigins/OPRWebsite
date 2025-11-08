// Greyline Studio Watermark Component
class GreylineStudioWatermark extends HTMLElement {
	constructor() {
		super();
		this.attachShadow({ mode: 'open' });
	}

	connectedCallback() {
		this.render();
	}

	render() {
		this.shadowRoot.innerHTML = `
            <style>
                :host {
                    position: fixed;
                    bottom: 20px;
                    left: 20px;
                    z-index: 9999;
                    pointer-events: auto;
                    display: block;
                }

                .watermark {
                    background: #282A36;
                    border: none;
                    border-radius: 25px;
                    padding: 10px 16px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    text-decoration: none;
                    color: #F8F8F2;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    font-size: 12px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
                }

                .watermark:hover {
                    background: linear-gradient(90deg, #5a5d7b, #7b6ba5);
                    border-color: rgba(180, 180, 220, 0.5);
                    color: #ffffff;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 20px rgba(150, 150, 200, 0.5), 0 0 20px rgba(150, 150, 200, 0.4);
                    filter: drop-shadow(0 0 8px rgba(150, 150, 200, 0.6));
                }

                .watermark:active {
                    transform: translateY(0);
                }

                .logo {
                    width: 18px;
                    height: 18px;
                    background: #B0B0B0;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 10px;
                    font-weight: bold;
                    color: #44475A;
                    flex-shrink: 0;
                    transition: all 0.3s ease;
                }

                .watermark:hover .logo {
                    background: white;
                    color: #7b6ba5;
                }

                .text {
                    display: flex;
                    flex-direction: column;
                    line-height: 1.3;
                }

                .powered-by {
                    font-size: 10px;
                    font-weight: 400;
                    opacity: 0.9;
                    color: #F8F8F2;
                    transition: all 0.3s ease;
                }

                .watermark:hover .powered-by {
                    color: #ffffff;
                }

                .company-name {
                    font-weight: 600;
                    font-size: 12px;
                    color: #F8F8F2;
                    transition: all 0.3s ease;
                }

                .watermark:hover .company-name {
                    color: #ffffff;
                }

                @media (max-width: 768px) {
                    :host {
                        position: fixed;
                        bottom: 15px;
                        left: 15px;
                    }

                    .watermark {
                        padding: 6px 12px;
                        font-size: 11px;
                    }

                    .logo {
                        width: 14px;
                        height: 14px;
                        font-size: 7px;
                    }
                }

                @media (max-width: 480px) {
                    :host {
                        position: fixed;
                        bottom: 15px;
                        left: 15px;
                    }

                    .watermark {
                        padding: 5px 10px;
                        font-size: 10px;
                    }

                    .logo {
                        width: 12px;
                        height: 12px;
                        font-size: 6px;
                    }
                }
            </style>
            <a href="https://greylinestudio.com/" target="_blank" rel="noopener noreferrer" class="watermark">
                <div class="logo">G</div>
                <div class="text">
                    <span class="powered-by">Powered by</span><br>
                    <span class="company-name">Greyline Studio</span>
                </div>
            </a>
        `;
	}
}

// Register the custom element
customElements.define('greyline-studio-watermark', GreylineStudioWatermark);