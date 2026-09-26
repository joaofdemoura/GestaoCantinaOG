package com.example.cantina;

import android.content.Intent;
import android.os.Bundle;
import android.widget.EditText;
import android.widget.Toast;
import org.json.JSONObject;

public class MainActivity extends BaseActivity {
    private EditText nome,cpf,idade,senha;
    private boolean busy;
    @Override protected void onResume() {
        super.onResume();
        if(!Api.perfil.isEmpty()) Api.request(this,"POST","/logout",null,(data,error)->{});
        Api.clear();
    }
    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);setContentView(R.layout.activity_main);
        nome=findViewById(R.id.editTextText);cpf=findViewById(R.id.editTextText3);
        idade=findViewById(R.id.editTextText2);senha=findViewById(R.id.editTextText4);senha.setSaveEnabled(false);
        findViewById(R.id.bnt_cadastrar).setOnClickListener(v->entrar(true));
        findViewById(R.id.btnEntrar).setOnClickListener(v->entrar(false));
        findViewById(R.id.btnResponsavel).setOnClickListener(v->startActivity(new Intent(this,CadastroPaisActivity.class)));
    }
    private void entrar(boolean cadastro) {
        if(busy)return;
        String id=cpf.getText().toString().trim();
        if(!id.matches("[0-9]{11}")){cpf.setError("Informe os 11 dígitos do CPF.");return;}
        if(senha.length()<(cadastro?6:4)){senha.setError(cadastro?"Use pelo menos 6 caracteres.":"Informe sua senha.");return;}
        JSONObject body=new JSONObject();
        try {
            body.put("cpf",id).put("senha",senha.getText().toString());
            if(cadastro) {
                if(nome.getText().toString().trim().isEmpty()){nome.setError("Informe seu nome.");return;}
                int anos;try{anos=Integer.parseInt(idade.getText().toString());}catch(NumberFormatException e){anos=0;}
                if(anos<1||anos>120){idade.setError("Informe uma idade entre 1 e 120.");return;}
                body.put("nome",nome.getText().toString().trim()).put("idade",anos);
            }
        } catch(org.json.JSONException e){return;}
        busy=true;findViewById(R.id.bnt_cadastrar).setEnabled(false);findViewById(R.id.btnEntrar).setEnabled(false);
        Api.request(this,"POST",cadastro?"/alunos":"/login",body,(data,error)->{
            busy=false;findViewById(R.id.bnt_cadastrar).setEnabled(true);findViewById(R.id.btnEntrar).setEnabled(true);
            if(error!=null){Toast.makeText(this,error,Toast.LENGTH_LONG).show();return;}
            android.content.SharedPreferences prefs=getSharedPreferences("cantina",0);
            if(!id.equals(prefs.getString("alunoAtual","")))CarrinhoStore.limpar();
            prefs.edit().putString("alunoAtual",id).apply();Api.session(data);senha.setText("");
            startActivity(new Intent(this,CardapioTelaInicialActivity.class));
        });
    }
}
