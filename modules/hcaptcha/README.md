# hCaptcha Module

This module adds hCaptcha spam protection to your forms.

## Configuration

1. Sign up at https://www.hcaptcha.com/
2. Get your site key and secret key
3. Configure in Admin Panel > Modules > hCaptcha

## Usage

Add to your forms:
```php
echo executeHook('form_captcha', '');
```