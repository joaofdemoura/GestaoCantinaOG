package com.example.cantina;
import android.content.Intent;
import android.os.Bundle;
import android.widget.EditText;
import android.widget.Toast;
import org.json.JSONObject;

public class CadastroPaisActivity extends BaseActivity {
    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);setContentView(R.layout.cadastro_pais);
        EditText cpf=findViewById(R.id.et_cpf),senha=findViewById(R.id.et_senha);senha.setSaveEnabled(false);
        findViewById(R.id.bnt_cadastrar).setOnClickListener(v->{
            if(!cpf.getText().toString().trim().matches("[0-9]{11}")){cpf.setError("Informe os 11 dígitos do CPF.");return;}
            JSONObject body=new JSONObject();
            try{body.put("cpf",cpf.getText().toString().trim()).put("senha",senha.getText().toString());}catch(Exception e){return;}
            v.setEnabled(false);
            Api.request(this,"POST","/responsavel/login",body,(data,error)->{
                v.setEnabled(true);if(error!=null){Toast.makeText(this,error,Toast.LENGTH_LONG).show();return;}
                Api.session(data);senha.setText("");startActivity(new Intent(this,TelaPrincipalPaisActivity.class));finish();
            });
        });
    }
}
