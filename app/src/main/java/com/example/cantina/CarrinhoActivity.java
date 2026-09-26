package com.example.cantina;

import android.content.Intent;
import android.os.Bundle;
import android.widget.TextView;
import android.widget.Toast;

public class CarrinhoActivity extends BaseActivity {
    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);
        setContentView(R.layout.carrinho);
        findViewById(R.id.btnLimparCarrinho).setOnClickListener(v -> { CarrinhoStore.limpar(); onResume(); });
        findViewById(R.id.btn_Finalizar).setOnClickListener(v -> {
            if (CarrinhoStore.itens().isEmpty()) {
                Toast.makeText(this, "Adicione um produto primeiro.", Toast.LENGTH_SHORT).show();
                return;
            }
            Intent intent = new Intent(this, RelatorioPagamentoActivity.class);
            intent.putExtra("alunoNome", getIntent().getStringExtra("alunoNome"));
            intent.putExtra("alunoId", getIntent().getStringExtra("alunoId"));
            intent.putExtra("compraId", CarrinhoStore.pedidoId());
            startActivity(intent);
        });
    }
    @Override protected void onResume() {
        super.onResume();
        ((TextView)findViewById(R.id.nomeCarrinho)).setText(CarrinhoStore.itens().isEmpty()
            ? "Carrinho vazio" : CarrinhoStore.resumo());
        ((TextView)findViewById(R.id.totalCarrinho)).setText("Total: " + Moeda.formatar(CarrinhoStore.totalCentavos()));
    }
}
