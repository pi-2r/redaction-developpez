<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:output method="html" omit-xml-declaration="yes" version="1.0" encoding="UTF-8" indent="yes"/>

  <xsl:template match="/document">
    <html>
      <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <title>
          <xsl:choose>
            <xsl:when test="entete/titre/page"><xsl:value-of select="entete/titre/page"/></xsl:when>
            <xsl:otherwise>Article</xsl:otherwise>
          </xsl:choose>
        </title>
        <style>
          body{font-family: system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0f172a; line-height:1.7; margin:0;}
          header{padding:32px 16px; background:#f8fafc; border-bottom:1px solid #e5e7eb}
          .wrap{max-width:900px; margin:0 auto; padding:16px}
          h1{font-size:32px; margin:0 0 6px}
          .meta{color:#64748b; font-size:14px}
          h2{font-size:24px; margin-top:28px}
          p{margin:12px 0}
          figure{margin:16px 0;}
          figure img{max-width:100%; height:auto; border:1px solid #e5e7eb; border-radius:8px}
          figure figcaption{font-size:13px; color:#64748b}
          aside.synopsis{background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; padding:12px 14px; border-radius:8px; margin:16px 0}
          .soustitre img{max-height:28px; vertical-align:middle}
          code, pre{background:#0b1220; color:#e5e7eb; border-radius:6px}
          pre{padding:12px; overflow:auto}
          a{color:#2563eb}
          ol,ul{padding-left:24px}
          table{border-collapse:collapse; width:100%; margin:16px 0}
          th,td{border:1px solid #e5e7eb; padding:8px}
        </style>
      </head>
      <body>
        <header>
          <div class="wrap">
            <h1>
              <xsl:choose>
                <xsl:when test="entete/titre/article"><xsl:value-of select="entete/titre/article"/></xsl:when>
                <xsl:when test="entete/titre/page"><xsl:value-of select="entete/titre/page"/></xsl:when>
                <xsl:otherwise>Article</xsl:otherwise>
              </xsl:choose>
            </h1>
            <div class="meta">
              <xsl:if test="entete/date">Publié le <xsl:value-of select="entete/date"/> · </xsl:if>
              <xsl:if test="entete/licauteur">Auteur: <xsl:value-of select="entete/licauteur"/></xsl:if>
            </div>
            <div class="soustitre"><xsl:apply-templates select="soustitre/*"/></div>
          </div>
        </header>
        <main class="wrap">
          <xsl:apply-templates select="synopsis"/>
          <xsl:apply-templates select="summary"/>
        </main>
      </body>
    </html>
  </xsl:template>

  <xsl:template match="synopsis">
    <aside class="synopsis">
      <xsl:apply-templates/>
    </aside>
  </xsl:template>

  <xsl:template match="summary">
    <xsl:apply-templates/>
  </xsl:template>

  <xsl:template match="section">
    <section>
      <xsl:if test="@id"><a id="{@id}"></a></xsl:if>
      <xsl:apply-templates/>
    </section>
  </xsl:template>

  <xsl:template match="title"><h2><xsl:value-of select="."/></h2></xsl:template>
  <xsl:template match="paragraph"><p><xsl:apply-templates/></p></xsl:template>

  <xsl:template match="image">
    <figure>
      <img>
        <xsl:attribute name="src"><xsl:value-of select="@src"/></xsl:attribute>
        <xsl:if test="@alt"><xsl:attribute name="alt"><xsl:value-of select="@alt"/></xsl:attribute></xsl:if>
        <xsl:if test="@titre"><xsl:attribute name="title"><xsl:value-of select="@titre"/></xsl:attribute></xsl:if>
      </img>
      <xsl:if test="@legende"><figcaption><xsl:value-of select="@legende"/></figcaption></xsl:if>
    </figure>
  </xsl:template>

  <xsl:template match="link">
    <a>
      <xsl:attribute name="href"><xsl:value-of select="@href"/></xsl:attribute>
      <xsl:if test="@target"><xsl:attribute name="target"><xsl:value-of select="@target"/></xsl:attribute></xsl:if>
      <xsl:if test="@title"><xsl:attribute name="title"><xsl:value-of select="@title"/></xsl:attribute></xsl:if>
      <xsl:apply-templates/>
    </a>
  </xsl:template>

  <xsl:template match="liste">
    <xsl:choose>
      <xsl:when test="@type='1'">
        <ol><xsl:apply-templates select="element"/></ol>
      </xsl:when>
      <xsl:otherwise>
        <ul><xsl:apply-templates select="element"/></ul>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <xsl:template match="element"><li><xsl:apply-templates/></li></xsl:template>

  <xsl:template match="tableau">
    <div class="tableau"><xsl:apply-templates/></div>
  </xsl:template>

  <xsl:template match="code">
    <pre><code><xsl:value-of select="."/></code></pre>
  </xsl:template>

  <xsl:template match="text()|@*">
    <xsl:value-of select="."/>
  </xsl:template>
</xsl:stylesheet>

