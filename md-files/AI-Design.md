
## Design

https://therob.lol/wp-content/plugins/bandfront-player/debug-rest.php


we could hook into REST endpoints to get data

we could hook into the database to get data

/wc-analytics
/wc-analytics/customers
/wc-analytics/customers/(?P<id>[\d-]+)
/wc-analytics/leaderboards
/wc-analytics/leaderboards/allowed
/wc-analytics/leaderboards/(?P<leaderboard>\w+)
/wc-analytics/reports
/wc-analytics/reports/import
/wc-analytics/reports/import/cancel
/wc-analytics/reports/import/delete
/wc-analytics/reports/import/status
/wc-analytics/reports/import/totals
/wc-analytics/reports/(?P<type>[a-z]+)/export
/wc-analytics/reports/(?P<type>[a-z]+)/export/(?P<export_id>[a-z0-9]+)/status
/wc-analytics/reports/products
/wc-analytics/reports/variations
/wc-analytics/reports/products/stats
/wc-analytics/reports/variations/stats
/wc-analytics/reports/revenue/stats
/wc-analytics/reports/orders
/wc-analytics/reports/orders/stats
/wc-analytics/reports/categories
/wc-analytics/reports/taxes
/wc-analytics/reports/taxes/stats
/wc-analytics/reports/coupons
/wc-analytics/reports/coupons/stats
/wc-analytics/reports/stock
/wc-analytics/reports/stock/stats
/wc-analytics/reports/downloads
/wc-analytics/reports/downloads/stats
/wc-analytics/reports/customers
/wc-analytics/reports/customers/stats
/wc-analytics/reports/performance-indicators
/wc-analytics/reports/performance-indicators/allowed
/wc-analytics/admin/notes
/wc-analytics/admin/notes/(?P<id>[\d-]+)
/wc-analytics/admin/notes/delete/(?P<id>[\d-]+)
/wc-analytics/admin/notes/delete/all
/wc-analytics/admin/notes/tracker/(?P<note_id>[\d-]+)/user/(?P<user_id>[\d-]+)
/wc-analytics/admin/notes/update
/wc-analytics/admin/notes/experimental-activate-promo/(?P<promo_note_name>[\w-]+)
/wc-analytics/admin/notes/(?P<note_id>[\d-]+)/action/(?P<action_id>[\d-]+)
/wc-analytics/coupons
/wc-analytics/coupons/(?P<id>[\d]+)
/wc-analytics/coupons/batch
/wc-analytics/data
/wc-analytics/data/countries/locales
/wc-analytics/data/countries
/wc-analytics/data/countries/(?P<location>[\w-]+)
/wc-analytics/data/download-ips
/wc-analytics/orders
/wc-analytics/orders/(?P<id>[\d]+)
/wc-analytics/orders/batch
/wc-analytics/products
/wc-analytics/products/(?P<id>[\d]+)
/wc-analytics/products/batch
/wc-analytics/products/suggested-products
/wc-analytics/products/(?P<id>[\d]+)/duplicate
/wc-analytics/products/attributes
/wc-analytics/products/attributes/(?P<id>[\d]+)
/wc-analytics/products/attributes/batch
/wc-analytics/products/attributes/(?P<slug>[a-z0-9_\-]+)
/wc-analytics/products/attributes/(?P<attribute_id>[\d]+)/terms
/wc-analytics/products/attributes/(?P<attribute_id>[\d]+)/terms/(?P<id>[\d]+)
/wc-analytics/products/attributes/(?P<attribute_id>[\d]+)/terms/batch
/wc-analytics/products/attributes/(?P<slug>[a-z0-9_\-]+)/terms
/wc-analytics/products/categories
/wc-analytics/products/categories/(?P<id>[\d]+)
/wc-analytics/products/categories/batch
/wc-analytics/products/(?P<product_id>[\d]+)/variations
/wc-analytics/products/(?P<product_id>[\d]+)/variations/(?P<id>[\d]+)
/wc-analytics/products/(?P<product_id>[\d]+)/variations/batch
/wc-analytics/products/(?P<product_id>[\d]+)/variations/generate
/wc-analytics/variations
/wc-analytics/products/reviews
/wc-analytics/products/reviews/(?P<id>[\d]+)
/wc-analytics/products/reviews/batch
/wc-analytics/products/low-in-stock
/wc-analytics/products/count-low-in-stock
/wc-analytics/settings/(?P<group_id>[\w-]+)
/wc-analytics/settings/(?P<group_id>[\w-]+)/batch
/wc-analytics/settings/(?P<group_id>[\w-]+)/(?P<id>[\w-]+)
/wc-analytics/taxes
/wc-analytics/taxes/(?P<id>[\d]+)
/wc-analytics/taxes/batch
/wc/store
/wc/store/batch
/wc/store/cart
/wc/store/cart/add-item
/wc/store/cart/apply-coupon
/wc/store/cart/coupons
/wc/store/cart/coupons/(?P<code>[\w-]+)
/wc/store/cart/extensions
/wc/store/cart/items
/wc/store/cart/items/(?P<key>[\w-]{32})
/wc/store/cart/remove-coupon
/wc/store/cart/remove-item
/wc/store/cart/select-shipping-rate
/wc/store/cart/update-item
/wc/store/cart/update-customer
/wc/store/checkout
/wc/store/checkout/(?P<id>[\d]+)
/wc/store/order/(?P<id>[\d]+)
/wc/store/products/attributes
/wc/store/products/attributes/(?P<id>[\d]+)
/wc/store/products/attributes/(?P<attribute_id>[\d]+)/terms
/wc/store/products/categories
/wc/store/products/categories/(?P<id>[\d]+)
/wc/store/products/brands
/wc/store/products/brands/(?P<identifier>[\w-]+)
/wc/store/products/collection-data
/wc/store/products/reviews
/wc/store/products/tags
/wc/store/products
/wc/store/products/(?P<id>[\d]+)
/wc/store/products/(?P<slug>[\S]+)
/wc/store/v1
/wc/store/v1/batch
/wc/store/v1/cart
/wc/store/v1/cart/add-item
/wc/store/v1/cart/apply-coupon
/wc/store/v1/cart/coupons
/wc/store/v1/cart/coupons/(?P<code>[\w-]+)
/wc/store/v1/cart/extensions
/wc/store/v1/cart/items
/wc/store/v1/cart/items/(?P<key>[\w-]{32})
/wc/store/v1/cart/remove-coupon
/wc/store/v1/cart/remove-item
/wc/store/v1/cart/select-shipping-rate
/wc/store/v1/cart/update-item
/wc/store/v1/cart/update-customer
/wc/store/v1/checkout
/wc/store/v1/checkout/(?P<id>[\d]+)
/wc/store/v1/order/(?P<id>[\d]+)
/wc/store/v1/products/attributes
/wc/store/v1/products/attributes/(?P<id>[\d]+)
/wc/store/v1/products/attributes/(?P<attribute_id>[\d]+)/terms
/wc/store/v1/products/categories
/wc/store/v1/products/categories/(?P<id>[\d]+)
/wc/store/v1/products/brands
/wc/store/v1/products/brands/(?P<identifier>[\w-]+)
/wc/store/v1/products/collection-data
/wc/store/v1/products/reviews
/wc/store/v1/products/tags
/wc/store/v1/products
/wc/store/v1/products/(?P<id>[\d]+)
/wc/store/v1/products/(?P<slug>[\S]+)
/wc/private
/wc/private/patterns
/wc/v2
/wc/v2/products/brands
/wc/v2/products/brands/(?P<id>[\d]+)
/wc/v2/products/brands/batch
/wc/v3/products/brands
/wc/v3/products/brands/(?P<id>[\d]+)
/wc/v3/products/brands/batch
/wc/v1/connect/tos
/wc/v1/connect/account/settings
/wc/v1/connect/services/(?P<id>[a-z_]+)\/(?P<instance>[\d]+)
/wc/v1/connect/self-help
/wc/v1/connect/service-data-refresh
/wc/v1/connect/packages
/wc/v1/connect/label/(?P<order_id>\d+)
/wc/v1/connect/label/(?P<order_id>\d+)/(?P<label_ids>(\d+)(,\d+)*)
/wc/v1/connect/label/(?P<order_id>\d+)/(?P<label_id>\d+)/refund
/wc/v1/connect/label/preview
/wc/v1/connect/label/print
/wc/v1/connect/label/(?P<order_id>\d+)/rates
/wc/v1/connect/normalize-address
/wc/v1/connect/assets
/wc/v1/connect/shipping/carrier
/wc/v1/connect/subscription/(?P<subscription_key>.+)/activate
/wc/v1/connect/shipping/carrier/(?P<carrier_id>.+)
/wc/v1/connect/shipping/carrier-types
/wc/v1/connect/migration-flag
/wc/v1/connect/shipping/carriers
/wc/v1/connect/subscriptions
/wc/v1/connect/label/creation_eligibility
/wc/v1/connect/label/(?P<order_id>\d+)/creation_eligibility
/wc-admin/blueprint/export
/wc-admin/blueprint/import-step
/wc-admin/blueprint/import-allowed
/mailpoet-email-editor/v1
/mailpoet-email-editor/v1/send_preview_email
/mailpoet-email-editor/v1/get_personalization_tags
/wc/v1/coupons
/wc/v1/coupons/(?P<id>[\d]+)
/wc/v1/coupons/batch
/wc/v1/customers/(?P<customer_id>[\d]+)/downloads
/wc/v1/customers
/wc/v1/customers/(?P<id>[\d]+)
/wc/v1/customers/batch
/wc/v1/orders/(?P<order_id>[\d]+)/notes
/wc/v1/orders/(?P<order_id>[\d]+)/notes/(?P<id>[\d]+)
/wc/v1/orders/(?P<order_id>[\d]+)/refunds
/wc/v1/orders/(?P<order_id>[\d]+)/refunds/(?P<id>[\d]+)
/wc/v1/orders
/wc/v1/orders/(?P<id>[\d]+)
/wc/v1/orders/batch
/wc/v1/products/attributes/(?P<attribute_id>[\d]+)/terms
/wc/v1/products/attributes/(?P<attribute_id>[\d]+)/terms/(?P<id>[\d]+)
/wc/v1/products/attributes/(?P<attribute_id>[\d]+)/terms/batch
/wc/v1/products/attributes
/wc/v1/products/attributes/(?P<id>[\d]+)
/wc/v1/products/attributes/batch
/wc/v1/products/categories
/wc/v1/products/categories/(?P<id>[\d]+)
/wc/v1/products/categories/batch
/wc/v1/products/(?P<product_id>[\d]+)/reviews
/wc/v1/products/(?P<product_id>[\d]+)/reviews/(?P<id>[\d]+)
/wc/v1/products/shipping_classes
/wc/v1/products/shipping_classes/(?P<id>[\d]+)
/wc/v1/products/shipping_classes/batch
/wc/v1/products/tags
/wc/v1/products/tags/(?P<id>[\d]+)
/wc/v1/products/tags/batch
/wc/v1/products
/wc/v1/products/(?P<id>[\d]+)
/wc/v1/products/batch
/wc/v1/reports/sales
/wc/v1/reports/top_sellers
/wc/v1/reports
/wc/v1/taxes/classes
/wc/v1/taxes/classes/(?P<slug>\w[\w\s\-]*)
/wc/v1/taxes
/wc/v1/taxes/(?P<id>[\d]+)
/wc/v1/taxes/batch
/wc/v1/webhooks
/wc/v1/webhooks/(?P<id>[\d]+)
/wc/v1/webhooks/batch
/wc/v1/webhooks/(?P<webhook_id>[\d]+)/deliveries
/wc/v1/webhooks/(?P<webhook_id>[\d]+)/deliveries/(?P<id>[\d]+)
/wc/v2/coupons
/wc/v2/coupons/(?P<id>[\d]+)
/wc/v2/coupons/batch
/wc/v2/customers/(?P<customer_id>[\d]+)/downloads
/wc/v2/customers
/wc/v2/customers/(?P<id>[\d]+)
/wc/v2/customers/batch
/wc/v2/orders/(?P<order_id>[\d]+)/notes
/wc/v2/orders/(?P<order_id>[\d]+)/notes/(?P<id>[\d]+)
/wc/v2/orders/(?P<order_id>[\d]+)/refunds
/wc/v2/orders/(?P<order_id>[\d]+)/refunds/(?P<id>[\d]+)
/wc/v2/orders

examine this code for violating single class principle and mixing concerns

examine for best practices in wordpress 2025

make sure logic is grouped into the correct class 
should the player have a renderer does that mean it works for every render? including block and widget does that work in this design? 


concerns atm:
where is URL generation 
are all file operations happening inside files?


AUDIO ENGINE:
fails: HTML5 fallback
default: MEdia element
select: Wavesurfer
etc...