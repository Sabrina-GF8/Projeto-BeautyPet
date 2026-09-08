// Importa o componente Link para criar um link de navegação entre telas.
import { Link } from "expo-router";

// Importa o useState para armazenar os valores digitados nos campos.
import { useState } from "react";

// Importa componentes e recursos utilizados na tela de login.
import {
  Alert,
  Image,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

// Importa o botão personalizado do projeto.
import { Button } from "@/components/button";

// Importa o campo de entrada personalizado do projeto.
import { Input } from "@/components/input";


// Componente principal da tela de Login.
export default function Login() {

  // Armazena o email digitado pelo usuário.
  const [email, setEmail] = useState("");

  // Armazena a senha digitada pelo usuário.
  const [password, setPassword] = useState("");


  // Função executada quando o usuário pressiona o botão "Entrar".
  function handleSignIn() {

    // Verifica se o email ou a senha estão vazios.
    if (!email.trim() || !password.trim()) {

      // Exibe uma mensagem avisando que os campos precisam ser preenchidos.
      Alert.alert(
        "Entrar",
        "Preencha email e senha para entrar."
      );

      // Interrompe a execução da função.
      return;
    }

    // Exibe uma mensagem informando que o login foi realizado.
    Alert.alert(
      "Bem-vindo!",
      `O login foi realizado com ${email}`
    );
  }


  // Retorna a estrutura visual da tela.
  return (

    // Evita que o teclado cubra os campos quando estiver aberto.
    <KeyboardAvoidingView
      style={{ flex: 1 }}

      // Define o comportamento do teclado de acordo com o sistema operacional.
      behavior={Platform.select({
        ios: "padding",
        android: "height",
      })}
    >

      {/* Permite rolar a tela quando o conteúdo não couber na tela. */}
      <ScrollView
        contentContainerStyle={{ flexGrow: 1 }}

        // Permite tocar nos campos enquanto o teclado está aberto.
        keyboardShouldPersistTaps="handled"

        // Esconde a barra de rolagem vertical.
        showsVerticalScrollIndicator={false}
      >

        {/* Container principal da tela de login. */}
        <View style={styles.container}>

          {/* Imagem de fundo com o desenho de patas. */}
          <Image
            source={require("@/assets/fundo.jpeg")}
            style={styles.pata}
          />

          {/* Imagem/ilustração principal da tela. */}
          <Image
            source={require("@/assets/img1.png")}
            style={styles.ilustration}
          />


          {/* Área que recebe o efeito de sombra ao redor do formulário. */}
          <View style={styles.shadow}>

            {/* Área amarela que contém os textos e campos de login. */}
            <View style={styles.place}>

              {/* Título principal da tela. */}
              <Text style={styles.titulo}>
                Seja bem-vindo!
              </Text>

              {/* Texto de orientação para o usuário. */}
              <Text style={styles.subtitulo}>
                Acesse sua conta com email e senha.
              </Text>


              {/* Container que organiza os campos e o botão. */}
              <View style={styles.inputs}>

                {/* Campo utilizado para digitar o email. */}
                <Input
                  placeholder="E-mail"
                  keyboardType="email-address"
                  onChangeText={setEmail}
                  icon="email"
                />

                {/* Campo utilizado para digitar a senha. */}
                <Input
                  placeholder="Senha"
                  secureTextEntry
                  onChangeText={setPassword}
                  icon="password"
                />

                {/* Botão responsável por realizar o login. */}
                <Button
                  label="Entrar"
                  onPress={handleSignIn}
                />

              </View>
            </View>
          </View>


          {/* Texto exibido no final da tela para usuários sem conta. */}
          <Text style={styles.footerText}>
            Nao tem uma conta?{" "}

            {/* Link que leva o usuário para a tela de cadastro. */}
            <Link
              href="/cadastro"
              style={styles.footerLink}
            >
              Cadastre-se aqui.
            </Link>
          </Text>

        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}


// Define os estilos utilizados pelos componentes da tela.
const styles = StyleSheet.create({

  // Estilo do container principal da tela.
  container: {
    flex: 1,
    backgroundColor: "#ffffff",
    padding: 32,
  },


  // Estilo da imagem de patas utilizada como fundo.
  pata: {
    position: "absolute",
    width: "130%",
    height: "130%",
    top: 0,
    left: 0,
    opacity: 0.30,
  },


  // Estilo da imagem principal da tela.
  ilustration: {
    width: 178,
    height: 164,
    marginTop: 82,
    alignSelf: "center",
    transform: [{ translateY: -15 }],
  },


  // Define a sombra da área do formulário.
  shadow: {
    borderRadius: 14,

    // Configurações da sombra no iOS.
    shadowColor: "#000",
    shadowOffset: {
      width: 0,
      height: 3,
    },
    shadowOpacity: 0.15,
    shadowRadius: 5,

    // Configuração da sombra para Android.
    elevation: 4,

    // Move a área da sombra verticalmente.
    transform: [{ translateY: 26 }],
  },


  // Estilo da área amarela onde ficam os campos de login.
  place: {
    paddingHorizontal: 18,
    paddingVertical: 14,
    borderRadius: 16,
    backgroundColor: "#FADE84",
  },


  // Estilo do título "Seja bem-vindo!".
  titulo: {
    marginTop: -16,
    fontSize: 28,
    fontFamily: "Poppins_600SemiBold",
    transform: [{ translateY: 34 }],
  },


  // Estilo do texto abaixo do título.
  subtitulo: {
    fontSize: 14,
    marginTop: 18,
    fontFamily: "Poppins_500Medium",
    transform: [{ translateY: 22 }],
  },


  // Organiza os campos de entrada e o botão.
  inputs: {
    marginTop: 30,
    gap: 12,
  },


  // Estilo do texto que aparece no rodapé da tela.
  footerText: {
    textAlign: "center",
    marginTop: 62,
    color: "#585860",
    fontSize: 18,
    transform: [{ translateY: -5 }],
  },


  // Estilo do link "Cadastre-se aqui.".
  footerLink: {
    color: "#6a83f1",
    fontWeight: "700",
  },
});
