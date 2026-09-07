import 'package:flutter/material.dart';

class PriceText extends StatelessWidget {
  final int paise;
  final TextStyle? style;
  final bool showStrike;
  final int? strikePrice;

  const PriceText({
    super.key,
    required this.paise,
    this.style,
    this.showStrike = false,
    this.strikePrice,
  });

  static String format(int paise) {
    return '\u20B9${(paise / 100).toStringAsFixed(2)}';
  }

  @override
  Widget build(BuildContext context) {
    if (showStrike && strikePrice != null) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            format(paise),
            style: style ?? TextStyle(
              fontWeight: FontWeight.w600,
              color: Theme.of(context).colorScheme.primary,
            ),
          ),
          const SizedBox(width: 6),
          Text(
            format(strikePrice!),
            style: TextStyle(
              decoration: TextDecoration.lineThrough,
              color: Colors.grey[500],
              fontSize: 13,
            ),
          ),
        ],
      );
    }

    return Text(
      format(paise),
      style: style ?? TextStyle(
        fontWeight: FontWeight.w600,
        color: Theme.of(context).colorScheme.primary,
      ),
    );
  }
}
