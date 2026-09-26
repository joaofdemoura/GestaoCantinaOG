package com.example.cantina;

import java.io.Serializable;

public class ItemCarrinho implements Serializable {
    public final Produto produto;
    public int quantidade;
    public String observacao;

    public ItemCarrinho(Produto produto, int quantidade, String observacao) {
        this.produto = produto;
        this.quantidade = quantidade;
        this.observacao = observacao == null ? "" : observacao;
    }

    public long totalCentavos() {
        return produto.precoCentavos * quantidade;
    }
}
