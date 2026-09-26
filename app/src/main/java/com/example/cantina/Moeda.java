package com.example.cantina;

import java.math.BigDecimal;
import java.text.NumberFormat;
import java.util.Locale;

public final class Moeda {
    private Moeda() {}
    public static String formatar(long centavos) {
        return NumberFormat.getCurrencyInstance(new Locale("pt", "BR"))
                .format(BigDecimal.valueOf(centavos, 2));
    }
}
