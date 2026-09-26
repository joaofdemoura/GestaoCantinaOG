package com.example.cantina;

import android.content.Intent;
import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

public class ProdutoActivity extends BaseActivity {
    private Produto produto;
    private int quantidade = 1;
    private TextView quantidadeTexto;
    private TextView total;
    private EditText observacao;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.produto);

        if (savedInstanceState != null) quantidade = savedInstanceState.getInt("quantidade", 1);
        produto = (Produto) getIntent().getSerializableExtra("produto");
        if (produto == null) {
            Toast.makeText(this, "Produto inválido.", Toast.LENGTH_SHORT).show();
            finish();
            return;
        }

        TextView nome = findViewById(R.id.nomeProduto);
        TextView descricao = findViewById(R.id.descricaoProduto);
        TextView preco = findViewById(R.id.precoProduto);
        quantidadeTexto = findViewById(R.id.textQuantidade);
        total = findViewById(R.id.totalProduto);
        observacao = findViewById(R.id.observacaoProduto);

        nome.setText(produto.nome);
        descricao.setText(produto.descricao);
        preco.setText(produto.precoFormatado());
        atualizarQuantidade();

        findViewById(R.id.btnDiminuir).setOnClickListener(view -> {
            if (quantidade > 1) quantidade--;
            atualizarQuantidade();
        });

        findViewById(R.id.btnAumentar).setOnClickListener(view -> {
            if (quantidade < 999) quantidade++;
            atualizarQuantidade();
        });

        findViewById(R.id.btnLixeira).setOnClickListener(view -> finish());

        Button adicionar = findViewById(R.id.btnAdicionarCarrinho);
        adicionar.setOnClickListener(view -> {
            try { CarrinhoStore.adicionar(
                    produto,
                    quantidade,
                    observacao.getText().toString().trim()
            );
            } catch (IllegalArgumentException e) { Toast.makeText(this, e.getMessage(), Toast.LENGTH_SHORT).show(); return; }
            Toast.makeText(this, "Produto adicionado ao carrinho.", Toast.LENGTH_SHORT).show();
            finish();
        });
    }

    @Override protected void onSaveInstanceState(Bundle state) {
        super.onSaveInstanceState(state);
        state.putInt("quantidade", quantidade);
    }

    private void atualizarQuantidade() {
        quantidadeTexto.setText(String.valueOf(quantidade));
        total.setText(formatarReais(produto.precoCentavos * quantidade));
    }

    private String formatarReais(long centavos) {
        return "Total: " + Moeda.formatar(centavos);
    }
}
