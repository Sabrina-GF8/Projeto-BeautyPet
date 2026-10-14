import { Link, router} from "expo-router";
import { useState } from "react";
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

import { Button } from "@/components/button";
import { Input } from "@/components/input";
import { api } from "../lib/api";

export default function Signup() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [carregando, setCarregando] = useState(false);

  async function handleSignUp() {
    if (
      !name.trim() ||
      !email.trim() ||
      !password.trim() ||
      !confirmPassword.trim()
    ) {
      Alert.alert(
        "Cadastrar",
        "Preencha todos os campos para criar sua conta."
      );
      return;
    }

    if (password !== confirmPassword) {
      Alert.alert(
        "Cadastrar",
        "As senhas não são iguais."
      );
      return;
    }

    if (password.length < 6) {
      Alert.alert(
        "Cadastrar",
        "A senha precisa ter pelo menos 6 caracteres."
      );
      return;
    }

    if (carregando) {
      return;
    }

    try {
      setCarregando(true);

      const resposta = await api<{ mensagem?: string }>("cadastro", {
        nome: name.trim(),
        email: email.trim(),
        senha: password,
      });

      Alert.alert(
        "Cadastrar",
        resposta.mensagem || "Cadastro realizado com sucesso.",
        [
          {
            text: "OK",
            onPress: () => router.replace("/login"),
          },
        ]
      );
    } catch (erro) {
      Alert.alert(
        "Cadastrar",
        erro instanceof Error ? erro.message : "Não foi possível criar a conta."
      );
    } finally {
      setCarregando(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={{ flex: 1 }}
      behavior={Platform.select({
        ios: "padding",
        android: "height",
      })}
    >
      <ScrollView
        contentContainerStyle={{ flexGrow: 1 }}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.container}>

          <View pointerEvents="none" style={StyleSheet.absoluteFill}>
            <Image
              source={require("@/assets/fundo-login-cadastro.png")}
              style={styles.pata}
            />
          </View>

          <Image
            source={require("@/assets/img1.png")}
            style={styles.ilustration}
          />

          <View style={styles.shadow}>
            <View style={styles.place}>

              <Text style={styles.titulo}>
                Cadastrar
              </Text>

              <Text style={styles.subtitulo}>
                Crie sua conta para acessar
              </Text>

              <View style={styles.form}>

                <Input
                  placeholder="Nome"
                  icon="name"
                  value={name}
                  onChangeText={setName}
                />

                <Input
                  placeholder="E-mail"
                  keyboardType="email-address"
                  icon="email"
                  autoCapitalize="none"
                  autoCorrect={false}
                  value={email}
                  onChangeText={setEmail}
                />

                <Input
                  placeholder="Senha"
                  secureTextEntry
                  icon="password"
                  value={password}
                  onChangeText={setPassword}
                />

                <Input
                  placeholder="Confirmar a senha"
                  secureTextEntry
                  icon="password"
                  value={confirmPassword}
                  onChangeText={setConfirmPassword}
                />

                <Button
                  label={carregando ? "Cadastrando..." : "Cadastrar"}
                  onPress={handleSignUp}
                  disabled={carregando}
                />

              </View>
            </View>
          </View>

          <Text style={styles.footerText}>
            Já tem uma conta?{" "}
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
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#ffffff",
    padding: 32,
  },

  ilustration: {
    width: "100%",
    height: 200,
    resizeMode: "contain",
    marginTop: 20,
  },

  pata: {
    position: "absolute",
    width: "130%",
    height: "130%",
    top: 0,
    left: 0,
    opacity: 0.30,
  },

  titulo: {
    marginTop: 5,
    fontSize: 30,
    fontFamily: "Poppins_700Bold",
  },

  subtitulo: {
    fontSize: 18,
    fontFamily: "Poppins_600SemiBold",
    marginTop: -8,
  },

  form: {
    marginTop: -8,
    gap: 16,
  },

  footerText: {
    textAlign: "center",
    marginTop: 48,
    color: "#585860",
    fontSize: 18,
    transform: [{ translateY: -5 }],
    fontFamily: "Poppins_500Medium",
  },

  footerLink: {
    color: "#8397f1",
    fontWeight: "700",
  },

  shadow: {
    borderRadius: 14,

    shadowColor: "#000",
    shadowOffset: {
      width: 0,
      height: 3,
    },
    shadowOpacity: 0.15,
    shadowRadius: 5,

    elevation: 4,

    transform: [{ translateY: 26 }],

    zIndex: 1,
  },

  place: {
    padding: 20,
    borderRadius: 16,
    backgroundColor: "#FADE84",
  },
});
