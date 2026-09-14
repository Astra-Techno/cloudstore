import 'json_value.dart';

class Category {
  final int id;
  final String uuid;
  final String name;
  final String slug;
  final String? description;
  final String? imageUrl;
  final String status;
  final int? productCount;

  Category({
    required this.id,
    required this.uuid,
    required this.name,
    required this.slug,
    this.description,
    this.imageUrl,
    required this.status,
    this.productCount,
  });

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      id: JsonValue.integer(json['id']),
      uuid: JsonValue.string(json['uuid']),
      name: JsonValue.string(json['name'], 'Category'),
      slug: JsonValue.string(json['slug']),
      description: JsonValue.nullableString(json['description']),
      imageUrl: JsonValue.nullableString(json['image_url']),
      status: JsonValue.string(json['status'], 'active'),
      productCount: JsonValue.nullableInt(json['product_count']),
    );
  }
}
