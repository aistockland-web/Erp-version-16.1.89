# GitBook brand theme — maintainer setup

Apply these settings to the GitBook site after the documentation branch is connected with Git Sync. These are presentation settings only; they do not change ERP behavior or the certified 16.2.18 installer.

## Brand palette

| Purpose | Color | Use |
|---|---|---|
| Primary blue | `#145DA0` | Main links, active navigation, primary actions |
| Deep navy | `#08284B` | Cover, strong headings and dark surfaces |
| Orange accent | `#F28C28` | Important action cues and warning emphasis |
| Pale blue tint | `#F2F7FC` | Subtle page/background tint |
| White | `#FFFFFF` | Main light-mode surface |

Keep blue as the dominant interface color. Use orange sparingly so it signals an important action rather than competing with navigation.

## Apply in GitBook

1. Open the Stockland ERP documentation site in GitBook.
2. Open **Customization** from the site overview.
3. In the general branding and theme controls, set the primary color to `#145DA0`.
4. Choose a light, clean theme and use a pale blue tint (approximately `#F2F7FC`) if the tint control is available.
5. In **Semantic colors**, set **Info** to the primary blue and **Warning** to the orange accent `#F28C28`. Keep Success and Danger distinct and readable; do not use orange for every status.
6. Set the site title to **Stockland Ethiopia ERP User Manual**. Upload the approved Stockland logo as the site logo if the logo asset is available to the GitBook administrator.
7. Preview both light and dark modes. Check text contrast, hint blocks, navigation and mobile layout, then save/publish the customization.

GitBook may expose more branding controls depending on the workspace plan. If the tint or semantic-color controls are unavailable, keep the primary blue and use the orange cover artwork as the secondary accent.

## Git Sync project mapping

- Repository: `aistockland-web/Erp-version-16.1.89`
- Branch: `docs/stockland-erp-user-manual-16.2.18`
- Project directory: `docs/user-manual-16.2.18`
- Initial sync direction: GitHub → GitBook

Do not connect the documentation space to the production application branch. Do not merge documentation commits into the certified release branch.
