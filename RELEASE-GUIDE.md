# Release Guide for SNN Socials

## Quick Start

### Setup (One-time)

1. **Update GitHub repo URL** in `snn-socials.php` line 25:
   ```php
   new SNN_Socials_GitHub_Updater(
       __FILE__,
       'sinanisler/snn-socials', // ← Change to your repo
       ''
   );
   ```

2. **Initialize Git** (if not already done):
   ```bash
   git init
   git add .
   git commit -m "Initial commit"
   git remote add origin https://github.com/YOUR-USERNAME/snn-socials.git
   git push -u origin main
   ```

3. **Verify GitHub Actions** is enabled in your repo settings

## Creating a New Release

### Method 1: Auto-Bump Version (Easiest)

Just commit with "release" in the message:

```bash
git add .
git commit -m "release: Added Instagram support improvements"
git push
```

**What happens:**
- Version auto-increments (1.0.0 → 1.0.1)
- ZIP file created automatically
- GitHub release published
- Users get update notification in WordPress

### Method 2: Manual Version Control

1. **Edit version** in `snn-socials.php`:
   ```php
   * Version: 1.0.1  // ← Change this
   ```

2. **Commit and push:**
   ```bash
   git add snn-socials.php
   git commit -m "Bump version to 1.0.1"
   git push
   ```

3. **Create release commit:**
   ```bash
   git commit --allow-empty -m "release: Version 1.0.1"
   git push
   ```

## Version Numbering

Use [Semantic Versioning](https://semver.org/):

- **1.0.0** → **1.0.1** - Bug fixes (patch)
- **1.0.0** → **1.1.0** - New features (minor)
- **1.0.0** → **2.0.0** - Breaking changes (major)

## Workflow Behavior

### Triggers
- Workflow runs on every push to `main` branch
- Only creates release if commit message contains "release"

### What Gets Packaged
The ZIP includes everything EXCEPT:
- `.git/` folder
- `.github/` folder
- `node_modules/`
- `.DS_Store`
- `.gitignore`

### Release Naming
- Tag: `v1.0.0`
- ZIP: `snn-socials-v1.0.0.zip`

## Testing Before Release

```bash
# Test plugin locally
# Make sure all features work
# Test with different WordPress versions

# When ready
git add .
git commit -m "release: Ready for v1.0.1"
git push
```

## Checking Release Status

1. Go to your GitHub repo
2. Click "Actions" tab
3. See the workflow progress
4. Once complete, check "Releases" tab

## Users Get Updates Automatically

Once you create a release:

1. WordPress sites using your plugin will see update notification
2. They click "Update Now"
3. Plugin updates automatically from GitHub
4. No manual download needed!

## Troubleshooting

### Release not created?
- Check commit message contains "release"
- Verify workflow completed successfully in Actions tab
- Check if tag already exists

### Update not showing in WordPress?
- Version number must be higher than current
- WordPress checks for updates every 12 hours
- Force check: Dashboard → Updates → Check Again

### ZIP file missing?
- Check workflow logs in GitHub Actions
- Verify no errors during ZIP creation step

## Example Workflow

```bash
# 1. Make changes to your plugin
vim snn-socials.php

# 2. Test locally
# ... testing ...

# 3. Commit and release
git add .
git commit -m "release: Fixed X API authentication bug"
git push

# 4. Wait ~2 minutes for GitHub Actions to complete

# 5. Check GitHub Releases tab - new release is there!

# 6. Users automatically get update notification in WordPress
```

## Notes

- First release must be tagged manually or via workflow
- Subsequent releases are fully automated
- The workflow preserves git history
- Old releases remain available for download
- Users can roll back to any previous version

## Support

For issues with the release workflow:
- Check [GitHub Actions Documentation](https://docs.github.com/en/actions)
- Review workflow file: `.github/workflows/release.yml`
- Check workflow logs in Actions tab
