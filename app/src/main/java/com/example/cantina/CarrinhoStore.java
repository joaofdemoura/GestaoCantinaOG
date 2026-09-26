package com.example.cantina;

import android.content.Context;
import android.content.SharedPreferences;
import org.json.JSONArray;
import org.json.JSONObject;
import org.json.JSONException;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;

public final class CarrinhoStore {
    private static final List<ItemCarrinho> itens = new ArrayList<>();
    private static SharedPreferences prefs;
    private CarrinhoStore() {}
    public static void inicializar(Context context) {
        if (prefs != null) return;
        prefs = context.getApplicationContext().getSharedPreferences("cantina", Context.MODE_PRIVATE);
        if (!prefs.getBoolean("apiCart", false)) {
            prefs.edit().remove("carrinho").remove("pedidos").putBoolean("apiCart",true).apply();
        }
        try {
            JSONArray saved = new JSONArray(prefs.getString("carrinho", "[]"));
            for (int i = 0; i < saved.length(); i++) {
                JSONObject o = saved.getJSONObject(i);
                itens.add(new ItemCarrinho(new Produto(o.getString("id"), o.getString("nome"),
                    o.getString("descricao"), o.getLong("preco")), o.getInt("quantidade"), o.getString("observacao")));
            }
        } catch (JSONException e) { itens.clear(); }
    }
    public static List<ItemCarrinho> itens() { return Collections.unmodifiableList(itens); }
    public static void adicionar(Produto produto, int quantidade, String observacao) {
        if (produto == null || quantidade < 1 || quantidade > 999) throw new IllegalArgumentException("Quantidade inválida");
        String nota = observacao == null ? "" : observacao.trim();
        for (ItemCarrinho item : itens) {
            if (item.produto.id.equals(produto.id) && item.observacao.equals(nota)) {
                if (item.quantidade > 999 - quantidade) throw new IllegalArgumentException("Limite de 999 unidades por item");
                item.quantidade += quantidade;
                salvar();
                return;
            }
        }
        itens.add(new ItemCarrinho(produto, quantidade, nota));
        salvar();
    }
    private static void salvar() {
        JSONArray array = new JSONArray();
        try {
            for (ItemCarrinho item : itens) {
                JSONObject o = new JSONObject();
                o.put("id", item.produto.id).put("nome", item.produto.nome).put("descricao", item.produto.descricao)
                    .put("preco", item.produto.precoCentavos).put("quantidade", item.quantidade).put("observacao", item.observacao);
                array.put(o);
            }
        } catch (JSONException e) { throw new IllegalStateException(e); }
        if (prefs != null) prefs.edit().putString("carrinho", array.toString()).remove("pedidoId").apply();
    }
    public static String pedidoId() {
        String id=prefs.getString("pedidoId",null);
        if(id==null) {id=java.util.UUID.randomUUID().toString();prefs.edit().putString("pedidoId",id).apply();}
        return id;
    }
    public static long totalCentavos() {
        long total = 0;
        for (ItemCarrinho item : itens) total += item.totalCentavos();
        return total;
    }
    public static String resumo() {
        StringBuilder texto = new StringBuilder();
        for (ItemCarrinho item : itens) {
            texto.append(item.quantidade).append("x ").append(item.produto.nome).append(" — ")
                .append(Moeda.formatar(item.totalCentavos())).append('\n');
            if (!item.observacao.isEmpty()) texto.append("Obs.: ").append(item.observacao).append('\n');
        }
        return texto.toString().trim();
    }
    public static void limpar() { itens.clear(); salvar(); }
}
