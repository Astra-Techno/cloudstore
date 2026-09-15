import 'json_value.dart';

class CartData {
  final List<CartItem> items;
  final int subtotal;

  CartData({required this.items, required this.subtotal});

  factory CartData.fromJson(Map<String, dynamic> json) {
    final items = JsonValue.objectList(json['items'])
        .map(CartItem.fromJson)
        .toList();
    final subtotal = JsonValue.nullableInt(json['subtotal']) ??
        items.fold<int>(0, (sum, item) => sum + item.lineTotal);
    return CartData(items: items, subtotal: subtotal);
  }
}

class CartItem {
  final int id;
  final String productUuid;
  final String productName;
  final String? variantUuid;
  final String? variantName;
  final int quantity;
  final int unitPrice;
  final int addonsPrice;
  final List<String> addonNames;
  final int lineTotal;
  final bool isAvailable;

  CartItem({
    required this.id,
    required this.productUuid,
    required this.productName,
    this.variantUuid,
    this.variantName,
    required this.quantity,
    required this.unitPrice,
    required this.addonsPrice,
    this.addonNames = const [],
    required this.lineTotal,
    this.isAvailable = true,
  });

  factory CartItem.fromJson(Map<String, dynamic> json) {
    final unitPrice = _toInt(json['unit_price']);
    final addonsPrice = _toInt(json['addons_price']);
    final quantity = _toInt(json['quantity']);
    final lineTotal = _toInt(json['line_total']);
    final addonNames = (json['addons'] as List? ?? [])
        .map((addon) => addon is Map ? addon['name']?.toString() : addon?.toString())
        .whereType<String>()
        .where((name) => name.isNotEmpty)
        .toList();
    return CartItem(
      id: JsonValue.integer(json['id']),
      productUuid: JsonValue.string(json['product_uuid']),
      productName: JsonValue.string(json['product_name'], 'Product'),
      variantUuid: JsonValue.nullableString(json['variant_uuid']),
      variantName: JsonValue.nullableString(json['variant_name']),
      quantity: quantity,
      unitPrice: unitPrice,
      addonsPrice: addonsPrice,
      addonNames: addonNames,
      lineTotal: lineTotal > 0 ? lineTotal : (unitPrice + addonsPrice) * quantity,
      isAvailable: JsonValue.boolean(json['is_available'], true),
    );
  }

  static int _toInt(dynamic value) => JsonValue.integer(value);
}
