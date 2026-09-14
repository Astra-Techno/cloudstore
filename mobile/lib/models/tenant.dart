import 'json_value.dart';

class Tenant {
  final int id;
  final String name;
  final String slug;
  final String businessType;
  final String status;

  Tenant({
    required this.id,
    required this.name,
    required this.slug,
    required this.businessType,
    required this.status,
  });

  factory Tenant.fromJson(Map<String, dynamic> json) {
    return Tenant(
      id: JsonValue.integer(json['id']),
      name: JsonValue.string(json['name']),
      slug: JsonValue.string(json['slug']),
      businessType: JsonValue.string(json['business_type']),
      status: JsonValue.string(json['status'], 'active'),
    );
  }
}

class TenantBranding {
  final String? primaryColor;
  final String? logoUrl;
  final String? tagline;

  TenantBranding({this.primaryColor, this.logoUrl, this.tagline});

  factory TenantBranding.fromJson(Map<String, dynamic> json) {
    return TenantBranding(
      primaryColor: JsonValue.nullableString(json['primary_color']),
      logoUrl: JsonValue.nullableString(json['logo_url']),
      tagline: JsonValue.nullableString(json['tagline']),
    );
  }
}
