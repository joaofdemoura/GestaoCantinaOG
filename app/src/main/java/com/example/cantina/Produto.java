package com.example.cantina;

import java.io.Serializable;

public class Produto implements Serializable {
    public final String id;
    public final String nome;
    public final String descricao;
    public final long precoCentavos;

    public Produto(String id, String nome, String descricao, long precoCentavos) {
        this.id = id;
        this.nome = nome;
        this.descricao = descricao;
        this.precoCentavos = precoCentavos;
    }

    public String precoFormatado() {
        return Moeda.formatar(precoCentavos);
    }
}
