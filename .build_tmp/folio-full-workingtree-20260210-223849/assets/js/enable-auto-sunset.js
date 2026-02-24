/**
 * Enable Auto Sunset Theme Switch
 * 
 * Helper script to enable auto sunset theme switching
 * Run this in browser console to enable auto sunset switching
 * 
 * @package Folio
 */

(function() {
    'use strict';
    
    // Ensure required tool is loaded
    if (typeof window.SunTimesCalculator === 'undefined') {
        console.error('SunTimesCalculator is not loaded. Please refresh and try again.');
        return;
    }
    
    // Enable auto sunset switching
    localStorage.setItem('folio-auto-sunset', 'true');
    
    // Remove manually selected theme so auto switching can take effect
    const manualTheme = localStorage.getItem('mpb-theme');
    if (manualTheme) {
        console.log('Detected manually selected theme:', manualTheme);
        console.log('Removing manual theme setting and enabling auto switch...');
        localStorage.removeItem('mpb-theme');
    }
    
    console.log('Auto sunset switching enabled.');
    console.log('Theme will switch automatically by your location and sunrise/sunset time.');
    console.log('Tip: refresh page to apply immediately.');
    
    // Ask whether to refresh now
    if (confirm('Auto sunset switching enabled.\n\nRefresh now to apply changes?')) {
        location.reload();
    }
})();

