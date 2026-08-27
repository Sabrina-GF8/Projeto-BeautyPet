import { Link } from "expo-router";
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

export default function Login() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  function handleSignIn() {
    if (!email.trim() || !password.trim()) {
      Alert.alert(
        "Entrar",
        "Preencha email e senha para entrar."
      );
      return;
    }

    Alert.alert(
      "Bem-vindo!",
      `O login foi realizado com ${email}`
    );
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

          <Image
            source={require("@/assets/fundo.jpeg")}
            style={styles.pata}
          />

          <Image
            source={require("@/assets/img1.png")}
            style={styles.ilustration}
          />

          <View style={styles.shadow}>
            <View style={styles.place}>
              <Text style={styles.titulo}>
                Seja bem-vindo!
              </Text>

              <Text style={styles.subtitulo}>
                Acesse sua conta com email e senha.
              </Text>

              <View style={styles.inputs}>
                <Input
                  placeholder="E-mail"
                  keyboardType="email-address"
                  onChangeText={setEmail}
                  icon="email"
                />

                <Input
                  placeholder="Senha"
                  secureTextEntry
                  onChangeText={setPassword}
                  icon="password"
                />

                <Button
                  label="Entrar"
                  onPress={handleSignIn}
                />
              </View>
            </View>
          </View>

          <Text style={styles.footerText}>
            Nao tem uma conta?{" "}
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

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#ffffff",
    padding: 32,
  },

  pata: {
    position: "absolute",
    width: "130%",
    height: "130%",
    top: 0,
    left: 0,
    opacity: 0.30,
  },

  ilustration: {
    width: 178,
    height: 164,
    marginTop: 82,
    alignSelf: "center",
    transform: [{ translateY: -15 }],
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
  },

  place: {
    paddingHorizontal: 18,
    paddingVertical: 14,
    borderRadius: 16,
    backgroundColor: "#FADE84",
  },

  titulo: {
    marginTop: -16,
    fontSize: 28,
    fontFamily: "Poppins_600SemiBold",
    transform: [{ translateY: 34 }],
  },

  subtitulo: {
    fontSize: 14,
    marginTop: 18,
    fontFamily: "Poppins_500Medium",
    transform: [{ translateY: 22 }],
  },

  inputs: {
    marginTop: 30,
    gap: 12,
  },

  footerText: {
    textAlign: "center",
    marginTop: 62,
    color: "#585860",
    fontSize: 18,
    transform: [{ translateY: -5 }],
  },

  footerLink: {
    color: "#6a83f1",
    fontWeight: "700",
  },
});
