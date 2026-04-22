/**
 * SCSS Asset Configuration
 * 
 * This file contains the SCSS variables and Bootstrap customization
 * Compile this file to generate the final CSS for the project
 */

/* ============================================
   CUSTOM VARIABLES
   ============================================ */

// Brand Colors
$primary: #355EFC;          // Primary blue
$secondary: #E93C05;        // Secondary orange
$tertiary: #555555;         // Text color
$light: #DFE4FD;            // Light background
$dark: #011A41;             // Dark background

// Typography
$font-family-base: 'Open Sans', sans-serif;
$headings-font-family: 'Jost', sans-serif;

$body-color: $tertiary;
$headings-color: $dark;
$headings-font-weight: 700;
$display-font-weight: 700;

$enable-responsive-font-sizes: true;

// Borders & Spacing
$border-color: $light;
$input-border-color: $light;
$border-radius: 8px;
$border-radius-sm: $border-radius;
$border-radius-lg: $border-radius;

// Links
$link-decoration: none;

// Layout
$enable-negative-margins: true;

/* ============================================
   BOOTSTRAP IMPORT
   ============================================ */

// Import Bootstrap 5 with customized variables above
@import "bootstrap/scss/bootstrap";

/* ============================================
   USAGE WITHIN PROJECT
   ============================================ */

/*
   After compilation, this SCSS generates:
   - public/css/bootstrap.min.css (Bootstrap with custom variables)
   
   Additional styling is handled by:
   - public/css/style.css (custom CSS file)
   
   To modify colors or Bootstrap features:
   1. Edit the variables above
   2. Recompile using one of the methods in SCSS_SETUP.md
   3. The compiled CSS will automatically apply to the website
*/
