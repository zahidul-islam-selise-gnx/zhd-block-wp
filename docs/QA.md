# QA Checklist

## Activation matrix

- Activate with Elementor enabled.
- Activate with Elementor disabled and confirm the plugin stays safe with admin notices.

## Widget checks

- Confirm `ZHD AI Copilot` appears in the Elementor category list.
- Confirm the `ZHD AI Copilot` widget appears in the panel and renders only inside the editor workspace.
- Confirm the AI copilot widget links back to `ZHD Blocks -> AI Studio`.

## Draft flow

- Generate a draft from a text brief only.
- Generate a draft from multiple screenshot references.
- Save a structured draft manually and confirm validation errors stay safe.
- Save an approved draft to the Elementor library and confirm it appears in the Elementor template picker.

## Updater flow

- Publish a higher GitHub Release tag.
- Clear caches from `ZHD Blocks -> Tools`.
- Confirm WordPress shows the update and opens the changelog/details modal.
