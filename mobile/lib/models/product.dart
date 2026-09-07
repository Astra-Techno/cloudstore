class Product {
  final int id;
  final String uuid;
  final String name;
  final String slug;
  final String? description;
  final int basePrice;
  final int? salePrice;
  final String pricingMode;
  final String unit;
  final String status;
  final String stockMode;
  final int? stockQuantity;
  final String? categoryName;
  final List<ProductVariant> variants;
  final List<AddonGroup> addonGroups;

  Product({
    required this.id,
    required this.uuid,
    required this.name,
    required this.slug,
    this.description,
    required this.basePrice,
    this.salePrice,
    required this.pricingMode,
    required this.unit,
    required this.status,
    required this.stockMode,
    this.stockQuantity,
    this.categoryName,
    this.variants = const [],
    this.addonGroups = const [],
  });

  int get effectivePrice => salePrice ?? basePrice;

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id'] as int,
      uuid: json['uuid'] as String,
      name: json['name'] as String,
      slug: json['slug'] as String,
      description: json['description'] as String?,
      basePrice: json['base_price'] as int,
      salePrice: json['sale_price'] as int?,
      pricingMode: json['pricing_mode'] as String? ?? 'fixed',
      unit: json['unit'] as String? ?? 'piece',
      status: json['status'] as String,
      stockMode: json['stock_mode'] as String? ?? 'unlimited',
      stockQuantity: json['stock_quantity'] as int?,
      categoryName: json['category_name'] as String?,
      variants: (json['variants'] as List<dynamic>?)
              ?.map((v) => ProductVariant.fromJson(v))
              .toList() ??
          [],
      addonGroups: (json['addon_groups'] as List<dynamic>?)
              ?.map((g) => AddonGroup.fromJson(g))
              .toList() ??
          [],
    );
  }
}

class ProductVariant {
  final int id;
  final String uuid;
  final String name;
  final int priceAdjustment;
  final String status;

  ProductVariant({
    required this.id,
    required this.uuid,
    required this.name,
    required this.priceAdjustment,
    required this.status,
  });

  factory ProductVariant.fromJson(Map<String, dynamic> json) {
    return ProductVariant(
      id: json['id'] as int,
      uuid: json['uuid'] as String,
      name: json['name'] as String,
      priceAdjustment: json['price_adjustment'] as int? ?? 0,
      status: json['status'] as String? ?? 'active',
    );
  }
}

class AddonGroup {
  final int id;
  final String name;
  final int minSelections;
  final int maxSelections;
  final List<AddonItem> items;

  AddonGroup({
    required this.id,
    required this.name,
    required this.minSelections,
    required this.maxSelections,
    required this.items,
  });

  factory AddonGroup.fromJson(Map<String, dynamic> json) {
    return AddonGroup(
      id: json['id'] as int,
      name: json['name'] as String,
      minSelections: json['min_selections'] as int? ?? 0,
      maxSelections: json['max_selections'] as int? ?? 1,
      items: (json['items'] as List<dynamic>?)
              ?.map((i) => AddonItem.fromJson(i))
              .toList() ??
          [],
    );
  }
}

class AddonItem {
  final int id;
  final String name;
  final int price;

  AddonItem({required this.id, required this.name, required this.price});

  factory AddonItem.fromJson(Map<String, dynamic> json) {
    return AddonItem(
      id: json['id'] as int,
      name: json['name'] as String,
      price: json['price'] as int? ?? 0,
    );
  }
}
