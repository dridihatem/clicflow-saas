import Alpine from 'alpinejs';
import { registerNovaPublicDemo } from './nova-public-demo';
import { registerMarketingBrand } from './marketing-brand';
import { registerMarketingMotion } from './marketing-motion';
import { registerMarketingTheme } from './marketing-theme';
import { bootMarketingHeroThree } from './marketing-hero-three';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    registerNovaPublicDemo(Alpine);
});

registerMarketingBrand();
registerMarketingMotion();
registerMarketingTheme();
bootMarketingHeroThree();
Alpine.start();
