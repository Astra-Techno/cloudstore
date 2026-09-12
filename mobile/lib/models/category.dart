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
      id: json['id'] as int,
      uuid: json['uuid'] as String,
      name: json['name'] as String,
      slug: json['slug'] as String,
      description: json['description'] as String?,
      imageUrl: json['image_url'] as String?,
      status: json['status'] as String,
      productCount: json['product_count'] as int?,
    );
  }
}
