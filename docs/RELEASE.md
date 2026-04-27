# Release Checklist

1. Update the plugin version in [zhd-elementor-blocks.php](/Volumes/CORSAIR/Plugins/zhd-block-wp/zhd-elementor-blocks.php).
2. Update changelog details in [README.md](/Volumes/CORSAIR/Plugins/zhd-block-wp/README.md).
3. Verify AI draft generation, Elementor-library saving, and the AI Copilot widget on a test site.
4. Create a Git tag in the format `vX.Y.Z`.
5. Push the tag to GitHub.
6. The GitHub Actions workflow in [.github/workflows/release-package.yml](/Volumes/CORSAIR/Plugins/zhd-block-wp/.github/workflows/release-package.yml) will create the GitHub Release automatically and upload `zhd-elementor-blocks.zip`.
7. On the first install, use that packaged release ZIP, not GitHub's source-code ZIP.
8. On a test WordPress site, clear the updater cache from `ZHD Blocks -> Tools` and verify that WordPress detects the new version.
