class CartData {
  final List<CartItem> items;
  final int subtotal;

  CartData({required this.items, required this.subtotal});

  factory CartData.fromJson(Map<String, dynamic> json) {
    final itemsList = json['items'] as List<dynamic>? ?? [];
    final items = itemsList.map((i) => CartItem.fromJson(i)).toList();
    final rawSubtotal = json['subtotal'];
    final subtotal = rawSubtotal is int
        ? rawSubtotal
        : rawSubtotal is String
            ? (int.tryParse(rawSubtotal) ?? 0)
            : items.fold(0, (sum, i) => sum + i.lineTotal);
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
      id: _toInt(json['id']),
      productUuid: json['product_uuid']?.toString() ?? '',
      productName: json['product_name']?.toString() ?? 'Product',
      variantUuid: json['variant_uuid']?.toString(),
      variantName: json['variant_name']?.toString(),
      quantity: quantity,
      unitPrice: unitPrice,
      addonsPrice: addonsPrice,
      addonNames: addonNames,
      lineTotal: lineTotal > 0 ? lineTotal : (unitPrice + addonsPrice) * quantity,
    );
  }

  static int _toInt(dynamic v) {
    if (v is int) return v;
    if (v is double) return v.toInt();
    if (v is String) return int.tryParse(v) ?? 0;
    return 0;
  }
}
