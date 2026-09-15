import 'json_value.dart';

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
  final List<ProductImage> images;
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
    this.images = const [],
    this.variants = const [],
    this.addonGroups = const [],
  });

  int get effectivePrice => salePrice ?? basePrice;

  bool get isAvailable =>
      status == 'active' &&
      !(stockMode == 'limited_stock' && (stockQuantity ?? 0) <= 0);

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: JsonValue.integer(json['id']),
      uuid: JsonValue.string(json['uuid']),
      name: JsonValue.string(json['name'], 'Product'),
      slug: JsonValue.string(json['slug']),
      description: JsonValue.nullableString(json['description']),
      basePrice: JsonValue.integer(json['base_price']),
      salePrice: JsonValue.nullableInt(json['sale_price']),
      pricingMode: JsonValue.string(json['pricing_mode'], 'fixed'),
      unit: JsonValue.string(json['unit'], 'piece'),
      status: JsonValue.string(json['status'], 'active'),
      stockMode: JsonValue.string(json['stock_mode'], 'unlimited'),
      stockQuantity: JsonValue.nullableInt(json['stock_quantity']),
      categoryName: JsonValue.nullableString(json['category_name']),
      images: JsonValue.objectList(json['images'])
          .map(ProductImage.fromJson)
          .toList(),
      variants: JsonValue.objectList(json['variants'])
          .map(ProductVariant.fromJson)
          .toList(),
      addonGroups: JsonValue.objectList(json['addon_groups'])
          .map(AddonGroup.fromJson)
          .toList(),
    );
  }
}

class ProductImage {
  final String url;
  final bool isPrimary;

  ProductImage({required this.url, required this.isPrimary});

  factory ProductImage.fromJson(Map<String, dynamic> json) => ProductImage(
        url: JsonValue.string(json['url']),
        isPrimary: JsonValue.boolean(json['is_primary']),
      );
}

class ProductVariant {
  final int id;
  final String uuid;
  final String name;
  final int price;
  final int? weightGrams;
  final String status;

  ProductVariant({
    required this.id,
    required this.uuid,
    required this.name,
    required this.price,
    this.weightGrams,
    required this.status,
  });

  /// For backward compat — treat price as adjustment when product is fixed-price
  int get priceAdjustment => price;

  factory ProductVariant.fromJson(Map<String, dynamic> json) {
    return ProductVariant(
      id: JsonValue.integer(json['id']),
      uuid: JsonValue.string(json['uuid']),
      name: JsonValue.string(json['name']),
      price: JsonValue.integer(
          json['price'] ?? json['price_adjustment']),
      weightGrams: JsonValue.nullableInt(json['weight_grams']),
      status: JsonValue.string(json['status'], 'active'),
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
      id: JsonValue.integer(json['id']),
      name: JsonValue.string(json['name']),
      minSelections: JsonValue.integer(json['min_selections']),
      maxSelections: JsonValue.integer(json['max_selections'], 1),
      items: JsonValue.objectList(json['items']).map(AddonItem.fromJson).toList(),
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
      id: JsonValue.integer(json['id']),
      name: JsonValue.string(json['name']),
      price: JsonValue.integer(json['price']),
    );
  }
}
