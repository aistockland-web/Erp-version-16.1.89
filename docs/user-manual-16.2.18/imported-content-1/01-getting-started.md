# 1. Getting started

## Device connection options

Stockland ERP 16.2.18 is accessed through its approved HTTPS web address. The same company-approved address is used from a supported desktop/laptop browser, a phone/tablet browser, or a company-approved workstation used for Retail POS. Management supplies the production address; staff must not substitute a development, preview or Cloudflare test address.

| Device / work option       | How to connect                                                                                                                            | Where to work                                                                                                                                                                                             |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Desktop or laptop          | Open the management-approved HTTPS ERP address in the browser and sign in with your assigned account.                                     | Use the left navigation or the command launcher.                                                                                                                                                          |
| Phone or tablet            | Open the same approved HTTPS address in the device browser, then sign in with your own account.                                           | Open the mobile navigation drawer. Install the PWA only when Stockland enables installation for that device.                                                                                              |
| Retail POS workstation     | Use an approved desktop or tablet browser with the same ERP address and an authorized Sales Employee or Branch Manager account.           | Open Sales & POS → Retail POS. This is access to the ERP's browser-based POS screen; it does not establish automatic pairing with a payment terminal, receipt printer, cash drawer or barcode peripheral. |
| External branch POS report | Keep the original branch POS report and use the supported manual XLSX/CSV upload, or the optional approved Windows Incoming-folder agent. | Sales & POS → Daily POS Import. Both methods stage the file; the Branch Manager separately reviews and RELEASES TO ERP. No vendor POS API is connected in 16.2.18.                                        |

## Connect a device to the ERP

{% stepper %}
{% step %}
### Choose an approved device

Use a Stockland-approved desktop/laptop, phone/tablet or Retail POS workstation with internet access and a supported browser. Do not use another employee's saved session.
{% endstep %}

{% step %}
### Open the production address

Type or open the HTTPS address supplied by Stockland Management. Check the address before entering credentials. If it shows a development, preview or Cloudflare test host, stop and request the approved URL.
{% endstep %}

{% step %}
### Sign in with your own account

Enter the assigned username and password. Never share credentials or create a shared staff login.
{% endstep %}

{% step %}
### Verify the session

Check your displayed user, ERP role, assigned branch/warehouse and the build label for 16.2.18. If the role or location is wrong, sign out and ask the administrator to correct the assignment before doing work.
{% endstep %}

{% step %}
### Open the correct work area

For direct ERP checkout, open Retail POS. For a completed external branch POS report, use Daily POS Import and follow the separate staging and Branch Manager release procedure.
{% endstep %}

{% step %}
### Optional phone installation

If Stockland has enabled PWA installation, use the browser's supported install option. Installation is only a convenient way to open the same ERP web application; it does not grant extra permissions or change your assigned role/location.
{% endstep %}

{% step %}
### Sign out on shared devices

Use the account/profile sign-out action when finished. Close the ERP tab on a shared workstation.
{% endstep %}
{% endstepper %}

## Device-to-ERP connection path

```mermaid
flowchart TD
    A["Choose approved desktop / laptop / phone / tablet / POS workstation"] --> B["Open Stockland-approved HTTPS production address"]
    B --> C["Sign in with personal assigned account"]
    C --> D["Verify role, branch / warehouse and 16.2.18 build"]
    D --> E{"Work path"}
    E -->|Direct ERP checkout| F["Sales & POS → Retail POS"]
    E -->|External branch POS report| G["Daily POS Import: upload / approved folder pickup"]
    G --> H["Stage and validate; Branch Manager reviews and releases"]
    F --> I["Complete only authorized actions"]
    H --> I
    I --> J["Sign out on shared device"]
```

_Training diagram: the supported device paths lead to the same role- and location-controlled ERP session. It illustrates browser access and the documented import workflow; it is not a screenshot or evidence of peripheral hardware integration._

## Open and sign in

{% stepper %}
{% step %}
### Open the approved Stockland ERP address

Use the production URL supplied by Stockland management. Do not substitute a development, preview or Cloudflare test URL.
{% endstep %}

{% step %}
### Enter your assigned account

Use the username and password provided by the authorized administrator. Never share credentials or use another employee's account.
{% endstep %}

{% step %}
### Confirm your identity and location

After sign-in, check your displayed user/profile identity and assigned branch or warehouse. If the location is incorrect, stop and request an administrator correction before processing transactions.

![Signed-in Command Center dashboard for a Branch Manager showing the user name, assigned location and release](.gitbook/assets/S01-landing-dashboard.png)

_Signed-in landing screen for a Branch Manager: user name and role at top right, assigned location (Goro Branch) in the header, and the Command Center dashboard. Rendered from the exact 16.2.18 front end with synthetic training data; the figures are not live Stockland results._
{% endstep %}

{% step %}
### Confirm the release

The ERP shell identifies the running build. This manual applies to the approved 16.2.18 production release only.
{% endstep %}
{% endstepper %}

## Figure 1 — First sign-in and navigation

```mermaid
flowchart TD
    A["Open approved HTTPS ERP address"] --> B["Enter your own assigned credentials"]
    B --> C["Check your displayed name"]
    C --> D["Check assigned branch or warehouse"]
    D --> E["Confirm version 16.2.18"]
    E --> F{"Device"}
    F -->|Phone| G["Open the mobile navigation menu"]
    F -->|Desktop| H["Use the left navigation"]
    G --> I["Choose an authorized module"]
    H --> I
    I --> J["Complete the task using its manual page"]
    J --> K["Sign out when finished"]
```

_Figure 1 shows the sign-in checks and the two navigation layouts. It does not depict an actual captured screen._

## Workspace layout

The application has a Stockland-branded shell, left navigation, top header and page content. On phones, open the mobile menu to reach navigation. On desktop, the navigation can be collapsed.

### Open mobile navigation

On a phone, select the menu control to open navigation. Then select an authorized module.

![Mobile navigation drawer open on a phone-sized screen](.gitbook/assets/S03-mobile-navigation.png)

_Sales Employee mobile navigation drawer on a phone-sized (390 px) screen. Exact 16.2.18 front end with synthetic training data. This image illustrates navigation only; it does not show a completed transaction._

## Command launcher

The application supports a command launcher. Press Ctrl + K on Windows/Linux or Command + K on macOS, then search for an authorized module and select it.

## Sign out

Use the account/profile menu and sign-out action. On a shared computer, close the ERP tab after signing out.

## Phone and PWA use

The application is responsive and includes PWA support. Use the approved HTTPS ERP URL. If your organization enables installation on a device, use the browser's supported install option. Installing the web app does not grant additional permissions.
