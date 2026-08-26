import { Link } from "expo-router";
import { useState } from "react";
import { Alert, Image, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/button";
import { Input } from "@/components/input";

export default function Login() {
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")

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
      behavior={Platform.select({ ios: "padding", android: "height" })}
    >
      <ScrollView
        contentContainerStyle={{ flexGrow: 1 }}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.container}>

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

              <View>
                <Input
                  placeholder="E-mail"
                  keyboardType="email-address"
                  onChangeText={setEmail}
                />

                <Input
                  placeholder="Senha"
                  secureTextEntry
                  onChangeText={setPassword}
                />

                <Button
                  label="Entrar"
                  onPress={handleSignIn}
                />
              </View>

              <Text style={styles.footerText}>
                Nao tem uma conta?{" "}
                <Link href="/signup" style={styles.footerLink}>
                  Cadastre-se aqui.
                </Link>
              </Text>

            </View>
          </View>

        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f8f5d0",
    padding: 32,
  },

  ilustration: {
    width: 174,
    height: 160,
    marginTop: 78,
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

    elevation: 8,

    transform: [{ translateY: 20 }],
  },

  place: {
    padding: 20,
    borderRadius: 14,
    backgroundColor: "#FADE84",
  },

  titulo: {
    marginTop: 10,
    fontSize: 28,
    fontFamily: "Poppins_600SemiBold",
    transform: [{ translateY: 34 }],
  },

  subtitulo: {
    fontSize: 18,
    fontWeight: 700,
    marginTop: 21,
    transform: [{ translateY: 22 }],
  },

  footerText: {
    textAlign: "center",
    marginTop: 28,
    color: "#585860",
    fontSize: 18,
    transform: [{ translateY: -5 }],
  },

  footerLink: {
    color: "#6a83f1",
    fontWeight: 700,
  },
});
