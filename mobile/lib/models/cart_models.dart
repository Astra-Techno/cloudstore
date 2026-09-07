class CartData {
  final List<CartItem> items;
  final int subtotal;

  CartData({required this.items, required this.subtotal});

  factory CartData.fromJson(Map<String, dynamic> json) {
    final itemsList = json['items'] as List<dynamic>? ?? [];
    final items = itemsList.map((i) => CartItem.fromJson(i)).toList();
    return CartData(
      items: items,
      subtotal: json['subtotal'] as int? ?? items.fold(0, (sum, i) => sum + i.lineTotal),
    );
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
    required this.lineTotal,
  });

  factory CartItem.fromJson(Map<String, dynamic> json) {
    return CartItem(
      id: json['id'] as int,
      productUuid: json['product_uuid'] as String? ?? '',
      productName: json['product_name'] as String? ?? 'Product',
      variantUuid: json['variant_uuid'] as String?,
      variantName: json['variant_name'] as String?,
      quantity: json['quantity'] as int,
      unitPrice: json['unit_price'] as int? ?? 0,
      addonsPrice: json['addons_price'] as int? ?? 0,
      lineTotal: json['line_total'] as int? ?? 0,
    );
  }
}
