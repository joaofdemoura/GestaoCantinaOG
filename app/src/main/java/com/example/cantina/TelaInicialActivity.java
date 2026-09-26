package com.example.cantina;

import android.content.Intent;
import android.os.Bundle;

public class TelaInicialActivity extends BaseActivity {
    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);
        setContentView(R.layout.tela_inicial);
        findViewById(R.id.bnt_aluno).setOnClickListener(v ->
                startActivity(new Intent(this, MainActivity.class)));
        findViewById(R.id.bnt_pai).setOnClickListener(v ->
                startActivity(new Intent(this, CadastroPaisActivity.class)));
    }

    @Override protected void onResume() {
        super.onResume();
        if (!Api.perfil.isEmpty()) Api.request(this, "POST", "/logout", null, (data, error) -> {});
        Api.clear();
    }
}