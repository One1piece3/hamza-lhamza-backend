<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvelle commande {{ $order->reference }}</title>
</head>
<body style="margin:0; padding:0; background:#f4ede8; font-family:Arial, sans-serif; color:#1f2937;">
    <div style="max-width:720px; margin:0 auto; padding:34px 18px;">
        <div style="background:#fffaf7; border:1px solid rgba(214,176,156,0.72); border-radius:30px; overflow:hidden; box-shadow:0 24px 50px rgba(155,94,76,0.14);">
            <div style="padding:26px 30px; background:radial-gradient(circle at top right, rgba(255,255,255,0.18), transparent 32%), linear-gradient(145deg, #2d2630 0%, #45323b 55%, #93534f 100%); color:#fff7f2;">
                <p style="margin:0 0 10px; font-size:12px; text-transform:uppercase; letter-spacing:1.4px; color:rgba(255,228,218,0.8);">Hamza Lhamza</p>
                <h1 style="margin:0; font-size:31px; line-height:1.1; font-family:Georgia, 'Times New Roman', serif;">Nouvelle commande recue</h1>
                <p style="margin:12px 0 0; font-size:14px; line-height:1.7; color:rgba(255,240,235,0.84);">
                    Une nouvelle commande vient d etre enregistree. Voici un recapitulatif clair pour la prise en charge.
                </p>
            </div>

            <div style="padding:28px 30px 30px;">
                <div style="display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; margin-bottom:22px;">
                    <div style="background:#ffffff; border:1px solid rgba(228,190,170,0.58); border-radius:18px; padding:16px;">
                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.9px; color:#9f7d72; margin-bottom:6px;">Reference</div>
                        <strong style="color:#1f2430; font-size:16px;">{{ $order->reference }}</strong>
                    </div>
                    <div style="background:#ffffff; border:1px solid rgba(228,190,170,0.58); border-radius:18px; padding:16px;">
                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.9px; color:#9f7d72; margin-bottom:6px;">Total</div>
                        <strong style="color:#d85d49; font-size:18px;">{{ number_format((float) $order->total, 2, '.', ' ') }} DH</strong>
                    </div>
                </div>

                <h2 style="margin:0 0 12px; font-size:18px; color:#1f2430; font-family:Georgia, 'Times New Roman', serif;">Informations client</h2>
                <div style="background:#ffffff; border:1px solid rgba(228,190,170,0.58); border-radius:20px; padding:18px 20px; margin-bottom:22px; line-height:1.9; color:#5a6170;">
                    <div><strong style="color:#1f2430;">Client :</strong> {{ $order->customer_name }}</div>
                    <div><strong style="color:#1f2430;">Email :</strong> {{ $order->customer_email ?: 'Non renseigne' }}</div>
                    <div><strong style="color:#1f2430;">Telephone :</strong> {{ $order->customer_phone }}</div>
                    <div><strong style="color:#1f2430;">Ville :</strong> {{ $order->customer_city }}</div>
                    <div><strong style="color:#1f2430;">Adresse :</strong> {{ $order->customer_address }}</div>
                    <div><strong style="color:#1f2430;">Note :</strong> {{ $order->customer_note ?: 'Aucune' }}</div>
                </div>

                <h2 style="margin:0 0 12px; font-size:18px; color:#1f2430; font-family:Georgia, 'Times New Roman', serif;">Articles commandes</h2>
                <div style="display:grid; gap:12px; margin-bottom:24px;">
                    @foreach ($order->items ?? [] as $item)
                        <div style="display:flex; justify-content:space-between; gap:14px; padding:15px 16px; background:linear-gradient(180deg, #fffdfb, #fff4ef); border:1px solid rgba(228,190,170,0.48); border-radius:18px;">
                            <div>
                                <strong style="display:block; color:#1f2430; margin-bottom:4px;">{{ $item['name'] ?? 'Produit' }}</strong>
                                <span style="font-size:13px; color:#6b7280; line-height:1.7;">
                                    Quantite: {{ $item['quantity'] ?? 0 }}
                                    @if (!empty($item['size'])) | Taille {{ $item['size'] }} @endif
                                    @if (!empty($item['color'])) | Couleur {{ $item['color'] }} @endif
                                </span>
                            </div>
                            <strong style="white-space:nowrap; color:#d85d49; font-size:15px;">
                                {{ number_format((float) (($item['price'] ?? 0) * ($item['quantity'] ?? 0)), 2, '.', ' ') }} DH
                            </strong>
                        </div>
                    @endforeach
                </div>

                <div style="background:rgba(255,126,95,0.08); border:1px solid rgba(255,126,95,0.16); border-radius:18px; padding:16px 18px;">
                    <p style="margin:0; font-size:14px; line-height:1.8; color:#5f6575;">
                        Prochaine etape recommandee : confirmer la commande dans l espace admin, puis la faire passer en livraison avant cloture finale.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
