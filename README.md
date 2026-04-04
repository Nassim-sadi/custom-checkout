# Custom Checkout Algeria

Custom Checkout Algeria is a specialized WordPress plugin for WooCommerce that simplifies and optimizes the checkout process specifically for the Algerian market. It replaces the default complex checkout form with a streamlined version containing only essential fields.

## 🚀 Features

- **Simplified Fields**: Removes unnecessary billing/shipping fields (Address 1/2, Company, Postcode, etc.) to increase conversion rates.
- **Full Name Integration**: Replaces First/Last name with a single "Full Name" field.
- **Wilaya & Commune Selection**: Integrated bilingual (Latin/Arabic) dropdowns for all 58 Algerian wilayas and their corresponding communes.
- **Algerian Phone Validation**: Strict validation for Algerian mobile numbers (prefixes: 05, 06, 07).
- **Delivery Options**: Simple radio button selection between "Home Delivery" (Livraison à domicile) and "Stop Desk" (Récupérer au bureau).
- **Admin Integration**: Custom fields are saved and displayed directly in the WooCommerce Order details in the WordPress admin.
- **Custom Success Message**: Tailored "Thank You" message in French.
- **Premium UI**: Modern styling for checkout fields, including custom radio button designs.

## 🛠️ Installation

1. Download or clone this repository into your WordPress `wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure WooCommerce is installed and activated.

## 📁 Project Structure

- `custom-checkout.php`: Main plugin logic and WooCommerce hooks.
- `assets/css/checkout.css`: Custom styling for the checkout form.
- `assets/js/checkout.js`: Logic for dynamic Wilaya/Commune selection and form handling.
- `assets/js/cities-data.js`: Complete database of Algerian Wilayas and Communes.

## 🔧 Workflow

The plugin works by hooking into `woocommerce_checkout_fields` to unset default fields and inject custom ones. It uses JavaScript to dynamically populate the Commune dropdown based on the selected Wilaya, providing a seamless user experience.

---
*Developed by [Nassim Studio](https://nassimstudio.com)*
