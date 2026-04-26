# QA Checklist

## Activation matrix

- Activate with Elementor + WooCommerce enabled.
- Activate with Elementor enabled and WooCommerce disabled.
- Activate with Elementor disabled and confirm the plugin stays safe with admin notices.

## Widget checks

- Confirm `ZHD CRO Blocks` appears in the Elementor category list.
- Confirm `Hero CRO`, `Product Buy Box`, and `Trust Badges` appear only when their dependencies are satisfied.
- Confirm each widget loads its own frontend styles correctly.

## Template flow

- Import `Home Hero` from `ZHD Blocks -> Templates`.
- Import `Product Single Biotech` on a WooCommerce-enabled site.
- Confirm missing or invalid JSON files surface a safe error.

## Updater flow

- Publish a higher GitHub Release tag.
- Clear caches from `ZHD Blocks -> Tools`.
- Confirm WordPress shows the update and opens the changelog/details modal.

