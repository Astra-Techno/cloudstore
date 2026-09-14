import 'package:cloudstore/models/app_notification.dart';
import 'package:cloudstore/models/category.dart';
import 'package:cloudstore/models/customer.dart';
import 'package:cloudstore/models/driver.dart';
import 'package:cloudstore/models/order.dart';
import 'package:cloudstore/models/product.dart';
import 'package:cloudstore/models/tenant.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('API model boundary', () {
    test('parses database-style catalog values without throwing', () {
      final product = Product.fromJson({
        'id': '12',
        'uuid': 'product-uuid',
        'name': 'Fresh Mutton',
        'slug': 'fresh-mutton',
        'base_price': '450',
        'sale_price': '399',
        'stock_quantity': '8',
        'images': [
          {'url': 'https://example.test/mutton.jpg', 'is_primary': '1'}
        ],
        'variants': [
          {'id': '1', 'uuid': 'variant-uuid', 'name': '500 g', 'price': '220'}
        ],
        'addon_groups': [
          {
            'id': '5',
            'name': 'Cutting',
            'min_selections': '0',
            'max_selections': '1',
            'items': [
              {'id': '8', 'name': 'Curry cut', 'price': '0'}
            ],
          }
        ],
      });

      expect(product.id, 12);
      expect(product.effectivePrice, 399);
      expect(product.images.single.isPrimary, isTrue);
      expect(product.variants.single.price, 220);
      expect(product.addonGroups.single.items.single.id, 8);
    });

    test('parses order, customer, driver and supporting model values safely', () {
      final order = Order.fromJson({
        'id': '20',
        'uuid': 'order-uuid',
        'order_number': 'CM-20',
        'subtotal': '1200',
        'delivery_fee': '25',
        'tax_amount': '0',
        'discount_amount': '10',
        'total': '1215',
        'items': [
          {
            'id': '2',
            'product_snapshot': '{"name":"Mutton"}',
            'quantity': '2',
            'unit_price': '600',
            'addons_price': '0',
            'line_total': '1200',
          }
        ],
      });
      final customer = Customer.fromJson({'id': 1, 'name': 123, 'phone': 98765});
      final category = Category.fromJson({'id': '3', 'name': 99, 'status': true});
      final driver = DriverDelivery.fromJson({
        'id': '7',
        'order_id': '20',
        'order_total': '1215',
        'earnings': '50',
      });
      final tenant = Tenant.fromJson({'id': '1', 'name': 'Shop'});
      final notification = AppNotification.fromJson({'id': '1', 'title': 123});

      expect(order.total, 1215);
      expect(order.items.single.quantity, 2);
      expect(customer.name, '123');
      expect(category.id, 3);
      expect(driver.orderTotal, 1215);
      expect(tenant.id, 1);
      expect(notification.title, '123');
    });
  });
}
