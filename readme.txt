=== Booth Booking ===
Contributors: hasnatahmad095
Tags: expo, booth, booking, woocommerce, event
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Interactive expo booth booking with clickable floor map and WooCommerce integration.

== Description ==

Booth Booking allows event organizers to sell exhibition booths visually using an interactive floor map instead of a traditional product list.

Upload your expo layout image, place clickable booths anywhere on the map, link them to WooCommerce products, and let exhibitors reserve and purchase booths directly from the layout.

Perfect for:
- Trade shows
- Business expos
- Conferences
- Startup events
- University fairs
- Property exhibitions
- Mall shop bookings

= How It Works =

1. Create an Expo Map (Custom Post Type).
2. Upload your event floor layout image.
3. Define booths using JSON with:
   - id
   - x / y position (percentage-based)
   - type
   - product_id
   - price (optional display)
   - admin_booked (optional manual booking control)
4. Link each booth to a WooCommerce product.
5. Display the map using:
   - Shortcode: [hasnat_bb_booth_map id="123"]
   - Elementor widget

Users click a booth → enter details → booth becomes reserved → product is added to cart via AJAX → checkout is completed through WooCommerce.

= Key Features =

= Interactive Floor Map =
- Upload custom expo map image
- Clickable booth markers
- Responsive percentage-based positioning
- Visual booth states:
  - Available
  - Temporarily Reserved
  - Booked
  - Admin Booked

= WooCommerce Integration =
- Each booth linked to a WooCommerce product
- AJAX add-to-cart functionality
- Uses WooCommerce stock system
- Final booking confirmed after successful checkout

= Exhibitor Details Collection =
When selecting a booth, users must enter:
- Full Name
- Email Address

These details:
- Are stored with the cart item
- Appear in WooCommerce order details
- Allow admin to identify booth owner easily
- Are visible inside WooCommerce → Orders

= Temporary Reservation System =
- Booth is temporarily reserved when added to cart
- Prevents multiple users from booking the same booth
- Reservation uses WordPress transients (~10 minutes)
- If checkout is not completed, booth becomes available again
- Final confirmation depends on WooCommerce stock

= Admin Manual Booking Control =
Admins can manually mark booths as already booked.

To do this, add the following inside booth JSON:

{
    "id" : "A1",
    "x" : 12.5,
    "y" : 30.2,
    "type" : "cp_outside",
    "type_label" : "CP Outside",
    "color" : "#00a65a",
    "product_id" : 123,
    "price" : 3500,
    "admin_booked" : true
 }

When admin_booked is set to true:
- Booth appears as fully booked on frontend
- Cannot be clicked or reserved
- Ignores WooCommerce stock
- Useful for offline bookings, VIP reservations, sponsor booths, or manual allocations

= Elementor Support =
- Dedicated Elementor widget
- Drag-and-drop placement
- No coding required
- Fully responsive

= Developer Friendly =
- JSON-based booth configuration
- Clean architecture
- Easy to extend
- Ready for future enhancements
- Source code on GitHub: https://github.com/hasnatahmad095/booth-booking

= Benefits for Event Organizers =

- Visual booth selling increases conversions
- Prevents double booking
- Supports manual offline allocations
- Easily identify booth owners via name & email
- Works with all WooCommerce payment gateways
- Simple setup

= Benefits for Exhibitors =

- Instantly see available booths
- Transparent pricing
- Clear booth status
- One-click reservation
- Secure checkout
- Simple detail submission

== Installation ==

= Minimum Requirements =
- WordPress 5.8+
- PHP 7.4+
- WooCommerce installed and activated

= Automatic Installation =

1. Go to Plugins → Add New
2. Search for "Booth Booking"
3. Click Install Now
4. Activate the plugin
5. Ensure WooCommerce is active

= Manual Installation =

1. Upload the booth-booking folder to:
   /wp-content/plugins/
2. Activate via WordPress Admin → Plugins
3. Create your first Expo Map

== Getting Started ==

After activation:

1. Install & activate WooCommerce
2. Create WooCommerce products (one per booth)
   - Set stock quantity = 1
   - Use Simple product type
3. Go to WP Admin → Expo Maps
4. Upload your floor layout image
5. Add booth JSON configuration
6. Publish the Expo Map
7. Add shortcode to a page or use Elementor widget

Your interactive booth booking system is ready.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. WooCommerce is required for cart, checkout, and stock handling.

= How does booth reservation work? =

When a booth is added to cart:
- It becomes temporarily reserved
- The reservation lasts approximately 10 minutes
- If checkout is completed, WooCommerce stock makes it permanently unavailable
- If cart expires or order is not completed, the booth becomes available again

= Can admin manually block booths? =

Yes. Add "admin_booked": true inside booth JSON to mark it as booked manually.

= Can two users book the same booth? =

No. If product stock is set to 1 and one user completes checkout, the booth becomes unavailable.

= Are exhibitor details stored? =

Yes. Full Name and Email entered during booth selection are stored in WooCommerce order item meta.

= Does this plugin send data externally or track users? =

No. The plugin does not send data to external services and does not track users.

= Is it compatible with Elementor? =

Yes. A dedicated Elementor widget is included.

= Can I use variable products? =

It is strongly recommended to use Simple Products with stock quantity set to 1 for each booth.

== Screenshots ==

1. Expo Map editor with the visual booth editor and booth list
2. Frontend interactive booth map with legend
3. Booths selected and ready to add to cart

== Changelog ==

= 1.0.0 =
* Initial release
* Interactive floor map with clickable booths
* Visual drag-and-drop booth editor
* WooCommerce cart, checkout and stock integration
* Temporary reservation system
* Admin manual booking control (admin_booked)
* Exhibitor Full Name and Email collection
* Elementor widget
