// Importa o componente Link para criar links entre as telas do aplicativo.
import { Link } from "expo-router";

// Importa os componentes básicos utilizados na construção da tela.
import { Image, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View } from "react-native";

// Importa o componente de botão personalizado do projeto.
import { Button } from "@/components/button";

// Importa o componente de campo de entrada personalizado.
import { Input } from "@/components/input";


// Componente principal da tela de cadastro.
export default function Signup(){

  // Retorna a estrutura visual da tela.
  return(

    // Evita que o teclado cubra os campos de cadastro.
    <KeyboardAvoidingView
      style={{flex: 1 }}
      behavior={Platform.select({ios: "padding", android:"height"})}
    >

      // Permite que o usuário role a tela quando necessário.
      <ScrollView
        contentContainerStyle={{ flexGrow: 1}}
        keyboardShouldPersistTaps= "handled"
        showsVerticalScrollIndicator={false}
      >

        // Container principal da tela de cadastro.
        <View style={styles.container}>

          // Exibe a imagem de ilustração utilizada na tela.
          <Image
            source={require("@/assets/img1.png")}
            style={styles.ilustration}
          />

          // Título principal da tela.
          <Text style={styles.titulo}>Cadastrar</Text>

          // Texto de orientação para o usuário.
          <Text style={styles.subtitulo}>
            Crie sua conta para acessar
          </Text>

          // Container que organiza os campos do formulário.
          <View style={styles.form}>

            // Campo para informar o nome do usuário.
            <Input placeholder="Nome" />

            // Campo para informar o endereço de e-mail.
            <Input
              placeholder="E-mail"
              keyboardType="email-address"
            />

            // Campo para criar a senha.
            // secureTextEntry oculta os caracteres digitados.
            <Input
              placeholder="Senha"
              secureTextEntry
            />

            // Campo para confirmar a senha criada.
            <Input
              placeholder="Confirmar a senha"
              secureTextEntry
            />

            // Botão utilizado para realizar o cadastro.
            <Button label="Cadastrar" />

          </View>

          // Texto exibido para usuários que já possuem uma conta.
          <Text style={styles.footerText}>

            // Espaço entre o texto e o link.
            Já tem uma conta? {" "}

            // Link que direciona o usuário para a tela de login.
            <Link
              href="/login"
              style={styles.footerLink}
            >
              Entre aqui.
            </Link>

          </Text>

        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  )
}


// Define os estilos utilizados pelos componentes da tela.
const styles = StyleSheet.create({

  // Estilo do container principal da tela.
  container: {
    flex: 1,
    backgroundColor: "#FFE07A",
    padding: 32,
  },

  // Estilo da imagem de ilustração.
  ilustration: {
    width: "100%",
    height: 200,
    resizeMode: "contain",
    marginTop: 20,
  },

  // Estilo do título "Cadastrar".
  titulo: {
    marginTop: 10,
    fontSize: 32,
    fontWeight: 900,
    fontFamily: "Poppins_700Bold",
  },

  // Estilo do texto abaixo do título.
  subtitulo: {
    fontSize: 18,
    fontWeight: 700,
    fontFamily: "Poppins_600SemiBold",
  },

  // Estilo do formulário que contém os campos e o botão.
  form: {
    marginTop: -20,
    gap: 12,
  },

  // Estilo do texto exibido no rodapé da tela.
  footerText: {
    textAlign: "center",
    marginTop: 24,
    color: "#585860",
  },

  // Estilo do link que leva para a tela de login.
  footerLink: {
    color: "#8397f1",
    fontWeight: 700,
  },
})
